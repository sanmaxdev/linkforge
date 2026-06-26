<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Finder\Finder;

/**
 * Extracts every translatable string — __('…') and @lang('…') — from the Blade
 * views and PHP source into lang/en.json (key = source string), so translators
 * (and the in-admin language manager) have the full key list to work from.
 * Existing en.json values are preserved. Run after adding new UI strings.
 */
class ScanLanguageStrings extends Command
{
    protected $signature = 'lang:scan';

    protected $description = 'Extract translatable strings into lang/en.json.';

    public function handle(): int
    {
        $keys = [];

        $finder = (new Finder)->files()->in([resource_path('views'), app_path()])->name(['*.blade.php', '*.php']);
        foreach ($finder as $file) {
            $code = $file->getContents();
            // __('…') / @lang('…') / trans('…') with single or double quotes.
            foreach (["~(?:__|@lang|trans)\(\s*'((?:\\\\.|[^'\\\\])*)'~", '~(?:__|@lang|trans)\(\s*"((?:\\\\.|[^"\\\\])*)"~'] as $pattern) {
                preg_match_all($pattern, $code, $m);
                foreach ($m[1] as $raw) {
                    if (str_contains($raw, '$') || $raw === '') {
                        continue; // skip interpolated / empty
                    }
                    $key = stripcslashes($raw);
                    $keys[$key] = $key;
                }
            }
        }

        $path = lang_path('en.json');
        if (! is_dir(lang_path())) {
            mkdir(lang_path(), 0755, true);
        }
        $existing = is_file($path) ? (json_decode(file_get_contents($path), true) ?: []) : [];
        $merged = array_merge($keys, array_intersect_key($existing, $keys)); // keep edited values, drop stale keys
        ksort($merged);

        file_put_contents($path, json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");

        $this->info('Extracted '.count($merged).' translatable string(s) into lang/en.json.');

        return self::SUCCESS;
    }
}
