<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\UpdateLog;
use App\Models\User;
use App\Services\Update\Updater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class UpdaterTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> dirs to clean up */
    private array $tmp = [];

    protected function tearDown(): void
    {
        foreach ($this->tmp as $path) {
            $this->rrmdir($path);
        }
        foreach (glob(storage_path('app/update-backups/*-1.1.0')) ?: [] as $dir) {
            $this->rrmdir($dir);
        }
        @unlink(storage_path('app/updates/pending.zip'));
        parent::tearDown();
    }

    private function tmpDir(string $prefix): string
    {
        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.$prefix.'_'.uniqid();
        mkdir($dir, 0777, true);
        $this->tmp[] = $dir;

        return $dir;
    }

    /**
     * Build an update zip. Pass $manifest = null to omit update.json. $files keys
     * are paths under files/, $raw keys are literal archive entry names.
     */
    private function makeZip(?array $manifest, array $files = [], array $raw = []): string
    {
        $path = $this->tmpDir('pkg').DIRECTORY_SEPARATOR.'update.zip';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        if ($manifest !== null) {
            $zip->addFromString('update.json', json_encode($manifest));
        }
        foreach ($files as $rel => $contents) {
            $zip->addFromString('files/'.$rel, $contents);
        }
        foreach ($raw as $name => $contents) {
            $zip->addFromString($name, $contents);
        }
        $zip->close();

        return $path;
    }

    private function rrmdir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir.DIRECTORY_SEPARATOR.$entry;
            is_dir($path) ? $this->rrmdir($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    public function test_inspect_reads_the_manifest(): void
    {
        $zip = $this->makeZip([
            'version' => '1.1.0',
            'requires' => '1.0.0',
            'name' => 'Spring release',
            'notes' => 'Adds widgets.',
        ], ['app/Foo.php' => '<?php']);

        $m = app(Updater::class)->inspect($zip);

        $this->assertSame('1.1.0', $m['version']);
        $this->assertSame('1.0.0', $m['requires']);
        $this->assertSame('Spring release', $m['name']);
        $this->assertSame('Adds widgets.', $m['notes']);
    }

    public function test_inspect_rejects_archive_without_manifest(): void
    {
        $zip = $this->makeZip(null, ['app/Foo.php' => '<?php']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('update.json');
        app(Updater::class)->inspect($zip);
    }

    public function test_issues_blocks_downgrade_and_unmet_requirement(): void
    {
        Setting::put('app_version', '1.0.0');
        $u = app(Updater::class);

        // Same or older version is rejected.
        $this->assertNotEmpty($u->issues(['version' => '1.0.0', 'requires' => '0.0.0']));
        $this->assertNotEmpty($u->issues(['version' => '0.9.0', 'requires' => '0.0.0']));

        // Newer version whose requirement isn't met is rejected.
        $this->assertNotEmpty($u->issues(['version' => '2.0.0', 'requires' => '1.5.0']));

        // Newer version with a met requirement is good to go.
        $this->assertSame([], $u->issues(['version' => '1.1.0', 'requires' => '1.0.0']));
    }

    public function test_apply_writes_files_backs_up_and_bumps_version(): void
    {
        Setting::put('app_version', '1.0.0');
        $base = $this->tmpDir('base');
        mkdir($base.'/config', 0777, true);
        file_put_contents($base.'/config/old.php', 'OLD');

        $zip = $this->makeZip(
            ['version' => '1.1.0', 'requires' => '1.0.0', 'name' => 'Test update', 'notes' => ''],
            ['config/old.php' => 'NEW', 'app/New.php' => 'fresh']
        );

        $log = app(Updater::class)->apply($zip, [
            'version' => '1.1.0', 'name' => 'Test update', 'notes' => '',
        ], $base);

        $this->assertSame('NEW', file_get_contents($base.'/config/old.php'), 'existing file overwritten');
        $this->assertSame('fresh', file_get_contents($base.'/app/New.php'), 'new file created');

        // The replaced file was backed up.
        $backups = glob(storage_path('app/update-backups/*-1.1.0/config/old.php'));
        $this->assertNotEmpty($backups);
        $this->assertSame('OLD', file_get_contents($backups[0]));

        $this->assertSame('1.1.0', Setting::get('app_version'));
        $this->assertSame(1, UpdateLog::where('version', '1.1.0')->count());
        $this->assertNotEmpty($log);
    }

    public function test_apply_rejects_zip_slip_paths(): void
    {
        Setting::put('app_version', '1.0.0');
        $base = $this->tmpDir('base');

        // Drive-letter / traversal entry that would escape the base dir.
        $zip = $this->makeZip(
            ['version' => '1.1.0', 'requires' => '1.0.0', 'name' => 'Evil', 'notes' => ''],
            [],
            ['files/../escape.php' => 'pwned']
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsafe path');
        app(Updater::class)->apply($zip, [
            'version' => '1.1.0', 'name' => 'Evil', 'notes' => '',
        ], $base);

        $this->assertFileDoesNotExist(dirname($base).'/escape.php');
    }

    public function test_updates_page_requires_admin(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.updates'))->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->withoutVite()->get(route('admin.updates'))->assertOk()->assertSee('Installed version');
    }

    public function test_admin_can_upload_inspect_and_discard_a_package(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $zip = $this->makeZip([
            'version' => '1.1.0', 'requires' => '1.0.0', 'name' => 'Premium pack', 'notes' => 'Notes here.',
        ], ['app/Foo.php' => '<?php']);
        $upload = new UploadedFile($zip, 'update.zip', 'application/zip', null, true);

        $this->actingAs($admin)
            ->post(route('admin.updates.upload'), ['package' => $upload])
            ->assertRedirect(route('admin.updates'));
        $this->assertFileExists(storage_path('app/updates/pending.zip'));

        $this->actingAs($admin)->withoutVite()->get(route('admin.updates'))
            ->assertOk()->assertSee('Premium pack')->assertSee('v1.1.0');

        $this->actingAs($admin)->post(route('admin.updates.discard'))->assertRedirect();
        $this->assertFileDoesNotExist(storage_path('app/updates/pending.zip'));
    }

    public function test_ajax_upload_returns_json_for_the_progress_bar(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $headers = ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'];

        // Valid package over XHR -> JSON with a redirect target.
        $zip = $this->makeZip([
            'version' => '1.1.0', 'requires' => '1.0.0', 'name' => 'Ajax pack', 'notes' => '',
        ], ['app/Foo.php' => '<?php']);
        $this->actingAs($admin)
            ->post(route('admin.updates.upload'), ['package' => new UploadedFile($zip, 'update.zip', 'application/zip', null, true)], $headers)
            ->assertOk()->assertJsonStructure(['redirect']);

        // Invalid archive over XHR -> 422 JSON with a message the bar can show.
        $junk = $this->tmpDir('junk').DIRECTORY_SEPARATOR.'bad.zip';
        file_put_contents($junk, 'nope');
        $this->actingAs($admin)
            ->post(route('admin.updates.upload'), ['package' => new UploadedFile($junk, 'bad.zip', 'application/zip', null, true)], $headers)
            ->assertStatus(422)->assertJsonStructure(['message']);
    }

    public function test_uploading_a_non_update_archive_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $junk = $this->tmpDir('junk').DIRECTORY_SEPARATOR.'not.zip';
        file_put_contents($junk, 'this is not a zip');
        $upload = new UploadedFile($junk, 'not.zip', 'application/zip', null, true);

        $this->actingAs($admin)
            ->post(route('admin.updates.upload'), ['package' => $upload])
            ->assertSessionHas('error');
        $this->assertFileDoesNotExist(storage_path('app/updates/pending.zip'));
    }
}
