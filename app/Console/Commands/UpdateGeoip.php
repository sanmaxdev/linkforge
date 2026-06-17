<?php

namespace App\Console\Commands;

use App\Services\Analytics\GeoipUpdater;
use Illuminate\Console\Command;

class UpdateGeoip extends Command
{
    protected $signature = 'geoip:update {--provider= : dbip|maxmind} {--edition= : country|city}';

    protected $description = 'Download / refresh the GeoIP database used for country + city analytics.';

    public function handle(GeoipUpdater $updater): int
    {
        try {
            $this->info($updater->update($this->option('provider') ?: null, $this->option('edition') ?: null));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
