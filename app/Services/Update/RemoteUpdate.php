<?php

namespace App\Services\Update;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Pulls updates from the author's relay and stages them for the EXISTING
 * review -> Apply flow (Updater::apply). Fail-closed at every step. Never
 * auto-applies: it only produces storage/app/updates/pending.zip.
 */
class RemoteUpdate
{
    public function __construct(private Updater $updater) {}

    public function configured(): bool
    {
        return (string) config('update.channel_url') !== '' && (string) Setting::get('license_code') !== '';
    }

    /**
     * Canonical manifest string that the author signs offline. MUST match
     * tools/update-sign.php and the relay's lf_canonical_manifest() byte-for-byte.
     */
    public static function canonicalManifest(array $m): string
    {
        return '{'
            .'"item_id":'.json_encode((string) ($m['item_id'] ?? ''), JSON_UNESCAPED_SLASHES)
            .',"key_id":'.json_encode((string) ($m['key_id'] ?? ''), JSON_UNESCAPED_SLASHES)
            .',"requires":'.json_encode((string) ($m['requires'] ?? ''), JSON_UNESCAPED_SLASHES)
            .',"sha256":'.json_encode((string) ($m['sha256'] ?? ''), JSON_UNESCAPED_SLASHES)
            .',"size":'.(int) ($m['size'] ?? 0)
            .',"version":'.json_encode((string) ($m['version'] ?? ''), JSON_UNESCAPED_SLASHES)
            .'}';
    }

    /**
     * Ask the relay whether an update is available. Fail-closed.
     *
     * @return array{available:bool, release?:array, support_expired?:bool, error?:string}
     */
    public function check(): array
    {
        $url = (string) config('update.channel_url');
        $code = (string) Setting::get('license_code');
        if ($url === '' || $code === '') {
            return ['available' => false, 'error' => 'No license or update channel is configured.'];
        }

        try {
            $res = Http::timeout(10)->acceptJson()->asJson()->post($url.'/update/check', [
                'purchase_code' => $code,
                'domain' => request()->getHost(),
                'item_id' => (string) config('linkforge.license.item_id'),
                'current_version' => $this->updater->currentVersion(),
            ]);
        } catch (\Throwable $e) {
            return ['available' => false, 'error' => 'The update server is unreachable right now.'];
        }

        if (! $res->ok() || $res->json('eligible') !== true) {
            return ['available' => false, 'error' => (string) ($res->json('message') ?: 'Updates are not available for this license.')];
        }

        $available = $res->json('update_available') === true;
        Setting::putMany([
            'update_last_checked' => now()->toIso8601String(),
            'update_available' => $available ? '1' : '0',
            'update_available_version' => $available ? (string) $res->json('release.version') : '',
        ]);

        return [
            'available' => $available,
            'support_expired' => (bool) $res->json('support_expired'),
            'release' => $available ? (array) $res->json('release') : null,
        ];
    }

    /** Verify the release manifest's Ed25519 signature against a BUNDLED public key. */
    public function verifySignature(array $release): bool
    {
        $keyId = (string) ($release['key_id'] ?? '');
        $keys = (array) config('update.public_keys');
        if ($keyId === '' || ! isset($keys[$keyId])) {
            return false; // unknown key id => never trust
        }
        $pub = base64_decode((string) $keys[$keyId], true);
        $sig = base64_decode((string) ($release['signature'] ?? ''), true);
        if (! is_string($pub) || ! is_string($sig) || strlen($pub) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            return false;
        }

        return sodium_crypto_sign_verify_detached($sig, self::canonicalManifest($release), $pub);
    }

    /**
     * Download, fully verify, and stage the release as storage/app/updates/pending.zip.
     * Throws on any failure (and leaves nothing staged).
     */
    public function downloadAndStage(array $release): void
    {
        $version = (string) ($release['version'] ?? '');
        $sha = strtolower((string) ($release['sha256'] ?? ''));
        $size = (int) ($release['size'] ?? 0);
        $cap = (int) config('update.max_package_bytes');
        $dl = (array) ($release['download'] ?? []);

        if ($size <= 0 || $size > $cap) {
            throw new RuntimeException('Update package size is missing or exceeds the limit.');
        }
        // Authoritative check first: the signature over the canonical manifest.
        if (! $this->verifySignature($release)) {
            throw new RuntimeException('Update signature did not verify. Aborted for safety.');
        }

        $dir = storage_path('app/updates');
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $free = @disk_free_space($dir);
        if ($free !== false && $free < $size * 3) {
            throw new RuntimeException('Not enough free disk space to download this update.');
        }

        $tmp = $dir.'/download-'.bin2hex(random_bytes(4)).'.zip';

        try {
            $res = Http::timeout(180)->withOptions(['stream' => true])->acceptJson()->asJson()->post((string) ($dl['url'] ?? ''), [
                'purchase_code' => (string) Setting::get('license_code'),
                'domain' => request()->getHost(),
                'version' => $version,
                'token' => (string) ($dl['token'] ?? ''),
            ]);
            if (! $res->ok()) {
                $serverMsg = trim((string) ($res->json('error') ?? ''));
                throw new RuntimeException('Download was refused by the update server (status '.$res->status().')'
                    .($serverMsg !== '' ? ': '.$serverMsg : '.'));
            }

            // Capped streaming write — a hostile relay cannot fill the disk past the cap.
            $body = $res->toPsrResponse()->getBody();
            $out = fopen($tmp, 'wb');
            $written = 0;
            while (! $body->eof()) {
                $chunk = $body->read(65536);
                $written += strlen($chunk);
                if ($written > $cap) {
                    fclose($out);
                    throw new RuntimeException('Downloaded package exceeds the size limit.');
                }
                fwrite($out, $chunk);
            }
            fclose($out);

            // Integrity: received bytes must match the signed sha256.
            if (! hash_equals($sha, strtolower((string) hash_file('sha256', $tmp)))) {
                throw new RuntimeException('Downloaded package failed its integrity check.');
            }

            // Reuse the existing manifest + version policy, and bind the zip to the signed manifest.
            $manifest = $this->updater->inspect($tmp);
            if ($problems = $this->updater->issues($manifest)) {
                throw new RuntimeException(implode(' ', $problems));
            }
            if ((string) $manifest['version'] !== $version
                || (string) $manifest['requires'] !== (string) ($release['requires'] ?? '')) {
                throw new RuntimeException('The package contents do not match the signed manifest.');
            }
            if (version_compare($version, (string) config('update.min_version', '1.0.0'), '<')) {
                throw new RuntimeException('Update is below the minimum allowed version.');
            }

            // Stage for the existing review -> Apply UI.
            @rename($tmp, $dir.'/pending.zip');
        } catch (\Throwable $e) {
            @unlink($tmp);
            throw $e;
        }
    }
}
