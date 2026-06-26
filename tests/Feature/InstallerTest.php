<?php

namespace Tests\Feature;

use App\Support\Installer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstallerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(); // plans/settings so the install flow has a 'business' plan
        // Simulate a fresh, not-yet-installed upload (the base TestCase marks installed).
        @unlink(Installer::lockPath());
    }

    protected function tearDown(): void
    {
        Installer::markInstalled((string) config('linkforge.version'));
        parent::tearDown();
    }

    public function test_requests_redirect_to_the_installer_when_not_installed(): void
    {
        $this->get('/')->assertRedirect(route('install.welcome'));
        $this->get('/login')->assertRedirect(route('install.welcome'));
    }

    public function test_installer_welcome_renders_requirements(): void
    {
        $this->get(route('install.welcome'))
            ->assertOk()
            ->assertSee('Requirements')
            ->assertSee('Writable paths');
    }

    public function test_installer_is_sealed_once_installed(): void
    {
        Installer::markInstalled('1.0.0');

        $this->get(route('install.welcome'))->assertRedirect('/');
        $this->get(route('install.database'))->assertRedirect('/');
    }

    public function test_database_step_validates_input(): void
    {
        $this->post(route('install.database.save'), [])
            ->assertSessionHasErrors(['site_name', 'app_url', 'db_host', 'db_database', 'db_username']);
    }

    public function test_account_step_requires_database_step_first(): void
    {
        $this->get(route('install.account'))->assertRedirect(route('install.database'));
    }

    public function test_admin_account_completes_the_install(): void
    {
        $this->withSession(['install.db' => true]);

        // Creating the admin account is the final step — it goes straight to the finish
        // (no license/purchase-code step in the open-source build).
        $this->post(route('install.account.save'), [
            'name' => 'Site Owner',
            'email' => 'owner@example.com',
            'password' => 'supersecret',
            'password_confirmation' => 'supersecret',
        ])->assertRedirect(route('install.complete'));

        $this->assertDatabaseHas('users', ['email' => 'owner@example.com', 'role' => 'admin', 'status' => 'active']);

        $this->get(route('install.complete'))->assertOk()->assertSee('installed');
        $this->assertTrue(Installer::isInstalled());
    }

    public function test_write_env_updates_and_appends_keys(): void
    {
        $dir = sys_get_temp_dir();
        $file = 'lf_env_'.uniqid().'.env';
        file_put_contents($dir.DIRECTORY_SEPARATOR.$file, "APP_NAME=Old\nFOO=bar\n");

        $this->app->useEnvironmentPath($dir);
        $this->app->loadEnvironmentFrom($file);

        Installer::writeEnv(['APP_NAME' => 'New Name', 'DB_HOST' => '127.0.0.1']);

        $out = (string) file_get_contents($dir.DIRECTORY_SEPARATOR.$file);
        $this->assertStringContainsString('APP_NAME="New Name"', $out);
        $this->assertStringContainsString('DB_HOST=127.0.0.1', $out);
        $this->assertStringContainsString('FOO=bar', $out); // untouched key preserved

        @unlink($dir.DIRECTORY_SEPARATOR.$file);
    }
}
