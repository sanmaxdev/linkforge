<?php

namespace App\Providers;

use App\Services\Analytics\GeoResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Open the GeoIP .mmdb reader at most once per request.
        $this->app->singleton(GeoResolver::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Keep indexed string columns within the 767-byte limit of older
        // shared-hosting MySQL (5.7 / utf8mb4). Critical for portability.
        Schema::defaultStringLength(191);

        // Surface lazy-loading / mass-assignment mistakes during development.
        Model::shouldBeStrict($this->app->isLocal());

        // @lfdate($date) prints a date in the operator's chosen format + timezone.
        Blade::directive('lfdate', fn ($expr) => "<?php echo \App\Support\Dates::format($expr); ?>");
    }
}
