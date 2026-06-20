<?php

namespace App\Support;

use Illuminate\Encryption\Encrypter;

/**
 * First-run install state + environment-file plumbing for the web installer.
 *
 * "Installed" is signalled by a lock file under storage/ (kept out of the
 * shipped zip), so a fresh cPanel upload with no database boots straight into
 * the installer, while a configured site never sees it again. The lock path is
 * environment-scoped so the test suite can simulate a fresh install without
 * disturbing a running dev instance.
 */
class Installer
{
    public static function lockPath(): string
    {
        return storage_path(app()->environment('testing') ? 'installed.testing' : 'installed');
    }

    public static function isInstalled(): bool
    {
        return is_file(self::lockPath());
    }

    public static function markInstalled(string $version): void
    {
        file_put_contents(self::lockPath(), json_encode([
            'version' => $version,
            'installed_at' => now()->toIso8601String(),
        ], JSON_PRETTY_PRINT)."\n");
    }

    public static function envPath(): string
    {
        return app()->environmentFilePath();
    }

    /** A fresh APP_KEY in Laravel's "base64:" form. */
    public static function generateAppKey(): string
    {
        return 'base64:'.base64_encode(Encrypter::generateKey(config('app.cipher')));
    }

    /**
     * Update (or append) KEY=value pairs in the .env file, seeding it from
     * .env.example the first time. Values are quoted when they need to be.
     *
     * @param  array<string,string>  $values
     */
    public static function writeEnv(array $values): void
    {
        $path = self::envPath();
        if (! is_file($path)) {
            $example = base_path('.env.example');
            file_put_contents($path, is_file($example) ? (string) file_get_contents($example) : '');
        }

        $contents = (string) file_get_contents($path);
        foreach ($values as $key => $value) {
            $contents = self::replaceOrAppend($contents, $key, (string) $value);
        }
        file_put_contents($path, $contents);
    }

    private static function replaceOrAppend(string $contents, string $key, string $value): string
    {
        $line = $key.'='.self::quote($value);
        $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

        if (preg_match($pattern, $contents)) {
            return (string) preg_replace($pattern, $line, $contents, 1);
        }

        return rtrim($contents, "\r\n")."\n".$line."\n";
    }

    private static function quote(string $value): string
    {
        if ($value === '') {
            return '';
        }

        // Wrap in double quotes when the value contains whitespace or shell-ish chars.
        return preg_match('/[\s#"\'=$]/', $value)
            ? '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"'
            : $value;
    }

    /**
     * Server requirements for a healthy install.
     *
     * @return list<array{label:string, ok:bool, hint:string}>
     */
    public static function requirements(): array
    {
        $php = '8.2.0';
        $ext = fn (string $name) => extension_loaded($name);

        $checks = [
            ['label' => 'PHP '.$php.' or newer', 'ok' => version_compare(PHP_VERSION, $php, '>='), 'hint' => 'Detected '.PHP_VERSION],
        ];

        foreach (['pdo_mysql', 'mbstring', 'openssl', 'tokenizer', 'ctype', 'json', 'fileinfo', 'curl', 'gd', 'zip', 'xml', 'bcmath'] as $name) {
            $checks[] = ['label' => $name.' extension', 'ok' => $ext($name), 'hint' => $ext($name) ? 'Enabled' : 'Enable it in cPanel → Select PHP Version → Extensions'];
        }

        return $checks;
    }

    /**
     * Writable paths the app needs.
     *
     * @return list<array{label:string, ok:bool, hint:string}>
     */
    public static function writableChecks(): array
    {
        $envTarget = is_file(self::envPath()) ? self::envPath() : base_path();

        $paths = [
            ['label' => '.env file', 'path' => $envTarget],
            ['label' => 'storage/', 'path' => storage_path()],
            ['label' => 'storage/framework/', 'path' => storage_path('framework')],
            ['label' => 'storage/logs/', 'path' => storage_path('logs')],
            ['label' => 'bootstrap/cache/', 'path' => base_path('bootstrap/cache')],
            ['label' => 'public/uploads/', 'path' => is_dir(public_path('uploads')) ? public_path('uploads') : public_path()],
        ];

        return array_map(fn ($p) => [
            'label' => $p['label'],
            'ok' => is_writable($p['path']),
            'hint' => is_writable($p['path']) ? 'Writable' : 'Set permissions to 755 (or 775)',
        ], $paths);
    }

    /** True when every requirement and writable check passes. */
    public static function ready(): bool
    {
        foreach ([...self::requirements(), ...self::writableChecks()] as $c) {
            if (! $c['ok']) {
                return false;
            }
        }

        return true;
    }
}
