<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\Update\RemoteUpdate;
use App\Services\Update\Updater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class RemoteUpdateTest extends TestCase
{
    use RefreshDatabase;

    private string $secret;

    private string $public;

    /** @var list<string> */
    private array $tmp = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Ephemeral signing keypair — proves the verify path without the offline key.
        $kp = sodium_crypto_sign_keypair();
        $this->secret = sodium_crypto_sign_secretkey($kp);
        $this->public = sodium_crypto_sign_publickey($kp);

        config([
            'update.public_keys' => ['test-key' => base64_encode($this->public)],
            'update.channel_url' => 'https://relay.test',
            'update.min_version' => '1.0.0',
            'update.max_package_bytes' => 80 * 1024 * 1024,
        ]);

        Setting::put('license_code', '11111111-2222-3333-4444-555555555555');
        Setting::put('app_version', '1.0.0');
    }

    protected function tearDown(): void
    {
        foreach ($this->tmp as $p) {
            @unlink($p);
        }
        @unlink(storage_path('app/updates/pending.zip'));
        foreach (glob(storage_path('app/updates/download-*.zip')) ?: [] as $f) {
            @unlink($f);
        }
        parent::tearDown();
    }

    private function makeZip(array $manifest, array $files = ['app/Foo.php' => '<?php']): string
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'rupkg_'.uniqid().'.zip';
        $this->tmp[] = $path;
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('update.json', json_encode($manifest));
        foreach ($files as $rel => $c) {
            $zip->addFromString('files/'.$rel, $c);
        }
        $zip->close();

        return $path;
    }

    private function sign(array $manifest): string
    {
        return base64_encode(sodium_crypto_sign_detached(RemoteUpdate::canonicalManifest($manifest), $this->secret));
    }

    /** A release dict shaped like the relay's /update/check response, signed over its own manifest. */
    private function releaseFor(string $zipPath, array $over = []): array
    {
        $m = array_merge([
            'item_id' => '', 'key_id' => 'test-key', 'requires' => '1.0.0',
            'sha256' => hash_file('sha256', $zipPath), 'size' => filesize($zipPath), 'version' => '1.1.0',
        ], $over);

        return $m + [
            'name' => 'Test release', 'notes' => 'Notes',
            'signature' => $this->sign($m),
            'download' => ['url' => 'https://relay.test/update/download', 'token' => 'tok', 'expires_in' => 300],
        ];
    }

    // --- check() ---------------------------------------------------------------

    public function test_check_reports_an_available_update(): void
    {
        $zip = $this->makeZip(['version' => '1.1.0', 'requires' => '1.0.0', 'name' => 'X', 'notes' => '']);
        Http::fake(['https://relay.test/update/check' => Http::response([
            'eligible' => true, 'update_available' => true, 'support_expired' => false, 'release' => $this->releaseFor($zip),
        ])]);

        $res = app(RemoteUpdate::class)->check();

        $this->assertTrue($res['available']);
        $this->assertSame('1.1.0', $res['release']['version']);
        $this->assertSame('1', Setting::get('update_available'));
        $this->assertSame('1.1.0', Setting::get('update_available_version'));
    }

    public function test_check_reports_up_to_date(): void
    {
        Http::fake(['https://relay.test/update/check' => Http::response(['eligible' => true, 'update_available' => false, 'up_to_date' => true])]);

        $res = app(RemoteUpdate::class)->check();

        $this->assertFalse($res['available']);
        $this->assertSame('0', Setting::get('update_available'));
    }

    public function test_check_fails_closed_when_not_eligible(): void
    {
        Http::fake(['https://relay.test/update/check' => Http::response(['eligible' => false, 'message' => 'revoked'], 403)]);

        $res = app(RemoteUpdate::class)->check();

        $this->assertFalse($res['available']);
        $this->assertNotEmpty($res['error']);
    }

    public function test_check_fails_closed_on_server_error(): void
    {
        Http::fake(['https://relay.test/update/check' => Http::response('boom', 500)]);

        $this->assertFalse(app(RemoteUpdate::class)->check()['available']);
    }

    // --- verifySignature() -----------------------------------------------------

    public function test_signature_verifies_and_rejects_tampering(): void
    {
        $zip = $this->makeZip(['version' => '1.1.0', 'requires' => '1.0.0', 'name' => 'X', 'notes' => '']);
        $release = $this->releaseFor($zip);
        $svc = app(RemoteUpdate::class);

        $this->assertTrue($svc->verifySignature($release));

        $this->assertFalse($svc->verifySignature(['version' => '9.9.9'] + $release), 'tampered version must fail');
        $this->assertFalse($svc->verifySignature(['key_id' => 'unknown'] + $release), 'unknown key id must fail');
    }

    // --- downloadAndStage() ----------------------------------------------------

    public function test_download_stages_a_valid_release(): void
    {
        $zip = $this->makeZip(['version' => '1.1.0', 'requires' => '1.0.0', 'name' => 'X', 'notes' => '']);
        Http::fake(['https://relay.test/update/download' => Http::response(file_get_contents($zip), 200, ['Content-Type' => 'application/zip'])]);

        app(RemoteUpdate::class)->downloadAndStage($this->releaseFor($zip));

        $this->assertFileExists(storage_path('app/updates/pending.zip'));
        $this->assertSame(hash_file('sha256', $zip), hash_file('sha256', storage_path('app/updates/pending.zip')));
    }

    public function test_download_rejects_tampered_bytes(): void
    {
        $zip = $this->makeZip(['version' => '1.1.0', 'requires' => '1.0.0', 'name' => 'X', 'notes' => '']);
        Http::fake(['https://relay.test/update/download' => Http::response('CORRUPTED-NOT-THE-ZIP', 200)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('integrity');
        app(RemoteUpdate::class)->downloadAndStage($this->releaseFor($zip));
    }

    public function test_download_rejects_a_bad_signature(): void
    {
        $zip = $this->makeZip(['version' => '1.1.0', 'requires' => '1.0.0', 'name' => 'X', 'notes' => '']);
        $release = $this->releaseFor($zip);
        $release['signature'] = base64_encode(str_repeat("\0", 64));
        Http::fake(['https://relay.test/update/download' => Http::response(file_get_contents($zip), 200)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('signature');
        app(RemoteUpdate::class)->downloadAndStage($release);
    }

    public function test_download_rejects_an_oversize_package(): void
    {
        config(['update.max_package_bytes' => 100]);
        $zip = $this->makeZip(['version' => '1.1.0', 'requires' => '1.0.0', 'name' => 'X', 'notes' => '']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('exceeds');
        app(RemoteUpdate::class)->downloadAndStage($this->releaseFor($zip, ['size' => 5_000_000]));
    }

    public function test_download_rejects_a_manifest_mismatch(): void
    {
        // The package's own update.json says 1.2.0, but the signed release claims 1.1.0.
        $zip = $this->makeZip(['version' => '1.2.0', 'requires' => '1.0.0', 'name' => 'X', 'notes' => '']);
        Http::fake(['https://relay.test/update/download' => Http::response(file_get_contents($zip), 200)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('do not match');
        app(RemoteUpdate::class)->downloadAndStage($this->releaseFor($zip, ['version' => '1.1.0']));
    }

    public function test_remote_staged_zip_is_appliable_by_the_existing_updater(): void
    {
        $zip = $this->makeZip(['version' => '1.1.0', 'requires' => '1.0.0', 'name' => 'Remote', 'notes' => ''], ['app/Remote.php' => 'fresh']);
        Http::fake(['https://relay.test/update/download' => Http::response(file_get_contents($zip), 200)]);

        app(RemoteUpdate::class)->downloadAndStage($this->releaseFor($zip));

        $u = app(Updater::class);
        $m = $u->inspect(storage_path('app/updates/pending.zip'));
        $this->assertSame('1.1.0', $m['version']);
        $this->assertSame([], $u->issues($m), 'the remote-staged package passes the same issues() gate as a manual upload');
    }

    public function test_download_surfaces_the_servers_refusal_reason(): void
    {
        $zip = $this->makeZip(['version' => '1.1.0', 'requires' => '1.0.0', 'name' => 'X', 'notes' => '']);
        Http::fake(['https://relay.test/update/download' => Http::response(['error' => 'Invalid or expired download token.'], 403)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid or expired download token');
        app(RemoteUpdate::class)->downloadAndStage($this->releaseFor($zip));
    }

    // --- controller routes -----------------------------------------------------

    public function test_remote_routes_require_admin(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('admin.updates.check'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.updates.download'))->assertForbidden();
    }

    public function test_admin_check_sets_the_badge(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $zip = $this->makeZip(['version' => '1.1.0', 'requires' => '1.0.0', 'name' => 'X', 'notes' => '']);
        Http::fake(['https://relay.test/update/check' => Http::response([
            'eligible' => true, 'update_available' => true, 'support_expired' => false, 'release' => $this->releaseFor($zip),
        ])]);

        $this->actingAs($admin)->post(route('admin.updates.check'))
            ->assertRedirect()
            ->assertSessionHas('status');
        $this->assertSame('1', Setting::get('update_available'));
    }
}
