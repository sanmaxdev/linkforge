<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use ZipArchive;

/**
 * Vendor-side: build an update package for the in-app updater.
 *
 *   php artisan update:package 1.1.0 \
 *       --path=app --path=resources --path=public/build --path=database/migrations \
 *       --name="March feature drop" --notes="Adds the store block." --requires=1.0.0
 *
 * Produces storage/app/updates/build/linkforge-<version>.zip containing update.json
 * plus a files/ tree mirroring each given path. Ship that zip to buyers; they upload
 * it under Admin > Updates. Paths are resolved relative to the app root.
 */
class PackageUpdate extends Command
{
    protected $signature = 'update:package
        {version : Semantic version of this release, e.g. 1.1.0}
        {--path=* : File or directory to include (relative to the app root, repeatable)}
        {--name= : Human-readable release name}
        {--notes= : Release notes shown to the operator}
        {--requires= : Minimum installed version required (defaults to the current shipped version)}
        {--out= : Output zip path (defaults to storage/app/updates/build/linkforge-<version>.zip)}';

    protected $description = 'Build an update ZIP package for the in-app updater.';

    public function handle(): int
    {
        $version = (string) $this->argument('version');
        $paths = (array) $this->option('path');

        if (empty($paths)) {
            $this->error('Provide at least one --path to include in the package.');

            return self::FAILURE;
        }

        $out = (string) ($this->option('out') ?: storage_path("app/updates/build/linkforge-{$version}.zip"));
        if (! is_dir(dirname($out))) {
            mkdir(dirname($out), 0755, true);
        }

        $zip = new ZipArchive;
        if ($zip->open($out, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->error("Could not create {$out}.");

            return self::FAILURE;
        }

        $zip->addFromString('update.json', json_encode([
            'version' => $version,
            'requires' => (string) ($this->option('requires') ?: config('linkforge.version', '1.0.0')),
            'name' => (string) ($this->option('name') ?: "Update {$version}"),
            'notes' => (string) ($this->option('notes') ?: ''),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $count = 0;
        foreach ($paths as $path) {
            $abs = base_path($path);
            if (is_file($abs)) {
                $zip->addFile($abs, 'files/'.str_replace('\\', '/', $path));
                $count++;
            } elseif (is_dir($abs)) {
                $count += $this->addDirectory($zip, $abs, trim(str_replace('\\', '/', $path), '/'));
            } else {
                $this->warn("Skipped missing path: {$path}");
            }
        }

        $zip->close();

        $this->info("Packaged {$count} file(s) into {$out}");
        $this->line('Version '.$version.', requires '.($this->option('requires') ?: config('linkforge.version', '1.0.0')));

        return self::SUCCESS;
    }

    private function addDirectory(ZipArchive $zip, string $absDir, string $relBase): int
    {
        $count = 0;
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($absDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($items as $item) {
            if (! $item->isFile()) {
                continue;
            }
            $rel = str_replace('\\', '/', substr($item->getPathname(), strlen($absDir) + 1));
            $zip->addFile($item->getPathname(), 'files/'.$relBase.'/'.$rel);
            $count++;
        }

        return $count;
    }
}
