<?php

namespace App\Services\Analytics;

use App\Models\Setting;
use GeoIp2\Database\Reader;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Downloads / refreshes the GeoIP database the app uses for country + city
 * analytics, so operators never have to find, decompress, and upload a database
 * by hand.
 *
 *  - DB-IP Lite (default): a gzipped .mmdb at a predictable monthly URL, no
 *    account required, CC-BY (attribution shown in the admin).
 *  - MaxMind GeoLite2 (optional): a .tar.gz fetched with the operator's free
 *    license key, for those who prefer it.
 *
 * The result is written to storage/app/geoip/geoip.mmdb, which the resolver
 * prefers over the bundled seed. Downloads + decompression are streamed so even
 * the ~120 MB City database stays within shared-hosting memory limits.
 */
class GeoipUpdater
{
    public const EDITIONS = [
        'country' => 'Country only (small, ~8 MB)',
        'city' => 'Country + City (large, ~120 MB)',
    ];

    public const PROVIDERS = [
        'dbip' => 'DB-IP Lite (free, no account)',
        'maxmind' => 'MaxMind GeoLite2 (free license key)',
    ];

    public function targetPath(): string
    {
        return storage_path('app/geoip/geoip.mmdb');
    }

    /**
     * Run an update for the given (or configured) provider + edition.
     *
     * @return string human-readable success message
     */
    public function update(?string $provider = null, ?string $edition = null): string
    {
        $provider = array_key_exists($provider ?? '', self::PROVIDERS) ? $provider : (string) Setting::get('geoip_provider', 'dbip');
        $provider = array_key_exists($provider, self::PROVIDERS) ? $provider : 'dbip';
        $edition = array_key_exists($edition ?? '', self::EDITIONS) ? $edition : (string) Setting::get('geoip_edition', 'country');
        $edition = array_key_exists($edition, self::EDITIONS) ? $edition : 'country';

        $dir = dirname($this->targetPath());
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $base = $dir.DIRECTORY_SEPARATOR.'.download-'.bin2hex(random_bytes(4));

        try {
            $mmdb = $provider === 'maxmind'
                ? $this->fetchMaxmind($edition, $base)
                : $this->fetchDbip($edition, $base);

            new Reader($mmdb); // throws if it is not a valid database

            $dest = $this->targetPath();
            if (! @rename($mmdb, $dest)) {
                copy($mmdb, $dest);
                @unlink($mmdb);
            }

            // Keep exactly one active .mmdb so the resolver is unambiguous.
            // (Compare by basename so it is robust to path-separator differences.)
            foreach (glob($dir.DIRECTORY_SEPARATOR.'*.mmdb') ?: [] as $f) {
                if (basename($f) !== basename($dest)) {
                    @unlink($f);
                }
            }

            Setting::putMany([
                'geoip_provider' => $provider,
                'geoip_edition' => $edition,
                'geoip_updated_at' => now()->toIso8601String(),
                'geoip_source' => ($provider === 'maxmind' ? 'MaxMind GeoLite2' : 'DB-IP Lite').' · '.ucfirst($edition),
            ]);

            return ($edition === 'city' ? 'City' : 'Country').' database installed from '
                .($provider === 'maxmind' ? 'MaxMind' : 'DB-IP').'.';
        } finally {
            foreach (glob($dir.DIRECTORY_SEPARATOR.'.download-*') ?: [] as $f) {
                @unlink($f);
            }
        }
    }

    /** DB-IP Lite: gzipped .mmdb. Tries the current month, then the previous one. */
    private function fetchDbip(string $edition, string $base): string
    {
        $gz = $base.'.gz';
        $downloaded = false;
        foreach ($this->recentMonths(2) as $ym) {
            if ($this->download("https://download.db-ip.com/free/dbip-{$edition}-lite-{$ym}.mmdb.gz", $gz)) {
                $downloaded = true;
                break;
            }
        }
        if (! $downloaded) {
            throw new RuntimeException('Could not download the DB-IP database. Make sure the server can reach download.db-ip.com.');
        }

        $mmdb = $base.'.mmdb';
        $this->gunzip($gz, $mmdb);

        return $mmdb;
    }

    /** MaxMind GeoLite2: a .tar.gz fetched with the license key; the .mmdb is inside. */
    private function fetchMaxmind(string $edition, string $base): string
    {
        $key = trim((string) Setting::get('geoip_maxmind_key'));
        if ($key === '') {
            throw new RuntimeException('Enter your MaxMind license key first (free from maxmind.com).');
        }

        $editionId = $edition === 'city' ? 'GeoLite2-City' : 'GeoLite2-Country';
        $url = "https://download.maxmind.com/app/geoip_download?edition_id={$editionId}&license_key={$key}&suffix=tar.gz";

        $tarGz = $base.'.tar.gz';
        if (! $this->download($url, $tarGz)) {
            throw new RuntimeException('MaxMind download failed. Check your license key and that the server can reach maxmind.com.');
        }

        return $this->extractMmdbFromTarGz($tarGz, $base);
    }

    /** @return list<string> e.g. ['2026-06', '2026-05'] */
    private function recentMonths(int $count): array
    {
        $out = [];
        for ($i = 0; $i < $count; $i++) {
            $out[] = now()->copy()->subMonthsNoOverflow($i)->format('Y-m');
        }

        return $out;
    }

    /** Stream a URL to a file. Returns false on any failure (caller decides). */
    private function download(string $url, string $dest): bool
    {
        try {
            $res = Http::timeout(600)->withOptions(['sink' => $dest])->get($url);
            if (! $res->successful() || ! is_file($dest) || filesize($dest) < 1000) {
                @unlink($dest);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            @unlink($dest);

            return false;
        }
    }

    /** Streamed gunzip (chunked) so large databases never load fully into memory. */
    private function gunzip(string $src, string $dest): void
    {
        $in = @gzopen($src, 'rb');
        $out = @fopen($dest, 'wb');
        if (! $in || ! $out) {
            throw new RuntimeException('Could not decompress the downloaded database.');
        }
        while (! gzeof($in)) {
            fwrite($out, gzread($in, 262144));
        }
        gzclose($in);
        fclose($out);
        @unlink($src);
    }

    /** Pull the single .mmdb member out of a MaxMind .tar.gz. */
    private function extractMmdbFromTarGz(string $tarGz, string $base): string
    {
        $tar = $base.'.tar';
        $this->gunzip($tarGz, $tar);

        try {
            $phar = new \PharData($tar);
            foreach (new \RecursiveIteratorIterator($phar) as $file) {
                if (str_ends_with((string) $file->getFilename(), '.mmdb')) {
                    $mmdb = $base.'.mmdb';
                    copy($file->getPathname(), $mmdb);
                    @unlink($tar);

                    return $mmdb;
                }
            }
        } catch (\Throwable $e) {
            @unlink($tar);
            throw new RuntimeException('Could not read the MaxMind archive: '.$e->getMessage());
        }

        @unlink($tar);
        throw new RuntimeException('No .mmdb file was found inside the MaxMind archive.');
    }
}
