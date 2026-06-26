<?php

namespace App\Services\Update;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\UpdateLog;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use ZipArchive;

/**
 * In-app updater. The vendor ships an update as a ZIP:
 *
 *   update.json          { "version": "1.1.0", "requires": "1.0.0", "name": "...", "notes": "..." }
 *   files/...            mirrors the app root — code, resources, compiled public/build assets,
 *                        and any new database/migrations/*.php
 *
 * Applying = back up overwritten files, copy files/* over the app (zip-slip guarded),
 * run new migrations, clear caches, and bump the stored version. New code takes
 * effect on the next request.
 */
class Updater
{
    public function currentVersion(): string
    {
        return (string) (Setting::get('app_version') ?: config('linkforge.version', '1.0.0'));
    }

    /**
     * Read + validate the manifest from an update ZIP.
     *
     * @return array{version:string,requires:string,name:string,notes:string}
     */
    public function inspect(string $zipPath): array
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('The file is not a valid ZIP archive.');
        }
        $raw = $zip->getFromName('update.json');
        $zip->close();

        if ($raw === false) {
            throw new RuntimeException('The archive is missing its update.json manifest.');
        }
        $m = json_decode($raw, true);
        if (! is_array($m) || empty($m['version'])) {
            throw new RuntimeException('The update manifest is invalid.');
        }

        return [
            'version' => (string) $m['version'],
            'requires' => (string) ($m['requires'] ?? '0.0.0'),
            'name' => (string) ($m['name'] ?? 'Update '.$m['version']),
            'notes' => (string) ($m['notes'] ?? ''),
        ];
    }

    /**
     * Blocking reasons this update can't be applied (empty array = good to go).
     *
     * @param  array{version:string,requires:string}  $manifest
     * @return list<string>
     */
    public function issues(array $manifest): array
    {
        $issues = [];
        $current = $this->currentVersion();

        if (version_compare($manifest['version'], $current, '<=')) {
            $issues[] = "This package is version {$manifest['version']}, but this install is already on {$current}.";
        }
        if (version_compare($current, $manifest['requires'], '<')) {
            $issues[] = "This update requires version {$manifest['requires']} or newer (this install is on {$current}).";
        }

        return $issues;
    }

    /**
     * Apply an update. $base lets tests target a temp directory instead of the app root.
     *
     * @param  array{version:string,name:string,notes:string}  $manifest
     * @return list<string> human-readable log lines
     */
    public function apply(string $zipPath, array $manifest, ?string $base = null, ?int $userId = null): array
    {
        $base = rtrim($base ?? base_path(), '/\\');
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Could not open the update archive.');
        }

        $backupDir = storage_path('app/update-backups/'.date('Ymd-His').'-'.$manifest['version']);
        $copied = 0;
        $migrationFound = false;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (! str_starts_with($name, 'files/') || str_ends_with($name, '/')) {
                continue;
            }
            $rel = substr($name, strlen('files/'));
            if ($rel === '' || str_contains($rel, '..') || str_starts_with($rel, '/') || preg_match('~^[A-Za-z]:~', $rel)) {
                $zip->close();
                throw new RuntimeException("Unsafe path in update archive: {$name}");
            }

            $target = $base.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $rel);
            $contents = $zip->getFromName($name);
            if ($contents === false) {
                continue;
            }

            if (is_file($target)) { // back up the file we are about to overwrite
                $bak = $backupDir.DIRECTORY_SEPARATOR.$rel;
                $this->ensureDir(dirname($bak));
                @copy($target, $bak);
            }
            $this->ensureDir(dirname($target));
            file_put_contents($target, $contents);
            $copied++;

            if (str_contains($rel, 'database/migrations/')) {
                $migrationFound = true;
            }
        }
        $zip->close();

        $log = ["{$copied} file(s) written."];
        if ($copied && is_dir($backupDir)) {
            $log[] = 'Replaced files were backed up under storage/app/update-backups.';
        }

        // Only run migrations / clear caches when applying to the live app.
        if ($base === rtrim(base_path(), '/\\')) {
            Artisan::call('migrate', ['--force' => true]);
            $log[] = $migrationFound ? 'Database migrations applied.' : 'No new migrations.';

            Artisan::call('optimize:clear');
            if (function_exists('opcache_reset')) {
                @opcache_reset();
            }
            $log[] = 'Caches cleared.';
        }

        Setting::put('app_version', $manifest['version']);
        UpdateLog::create([
            'version' => $manifest['version'],
            'name' => $manifest['name'],
            'notes' => $manifest['notes'],
            'user_id' => $userId,
            'created_at' => now(),
        ]);
        AuditLog::record('app.update', "Updated to v{$manifest['version']}");
        $log[] = "Now on version {$manifest['version']}.";

        return $log;
    }

    private function ensureDir(string $dir): void
    {
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}
