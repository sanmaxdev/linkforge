<?php

namespace App\Services\Analytics;

use GeoIp2\Database\Reader;

/**
 * Resolves a visitor's ISO country code.
 *
 * Order of preference:
 *   1. Cloudflare's CF-IPCountry header (free, instant, no database) — the
 *      recommended production setup puts Cloudflare in front of the app.
 *   2. A local MaxMind-format .mmdb (GeoLite2, or the no-account DB-IP /
 *      IPinfo "country lite" databases) at config('linkforge.geo.db_path').
 *
 * Registered as a singleton so the .mmdb reader is opened once per request.
 */
class GeoResolver
{
    private ?Reader $reader = null;

    private bool $readerResolved = false;

    /** Memoized City lookup for the most recent IP (one mmdb read per IP). */
    private ?string $cityIp = null;

    private ?\GeoIp2\Model\City $cityRec = null;

    public function country(?string $ip, ?string $cfCountry = null): ?string
    {
        return $this->normalize($cfCountry) ?? $this->fromDatabase($ip);
    }

    /**
     * City name, when available. Prefers Cloudflare's CF-IPCity header (enable
     * the "Add visitor location headers" managed transform), then a local
     * City-level .mmdb (GeoLite2-City / DB-IP City). Null when neither resolves.
     */
    public function city(?string $ip, ?string $cfCity = null): ?string
    {
        return $this->clean($cfCity, 120) ?? $this->clean($this->cityRecord($ip)?->city->name, 120);
    }

    /** Region / state name, when available (same sources as city). */
    public function region(?string $ip, ?string $cfRegion = null): ?string
    {
        return $this->clean($cfRegion, 80) ?? $this->clean($this->cityRecord($ip)?->mostSpecificSubdivision->name, 80);
    }

    private function normalize(?string $code): ?string
    {
        $code = strtoupper(trim((string) $code));

        // Cloudflare uses XX (unknown) and T1 (Tor) for non-countries.
        return preg_match('/^[A-Z]{2}$/', $code) && ! in_array($code, ['XX', 'T1'], true)
            ? $code
            : null;
    }

    private function fromDatabase(?string $ip): ?string
    {
        if (! $ip || $ip === '127.0.0.1' || $ip === '::1') {
            return null;
        }

        $reader = $this->reader();
        if (! $reader) {
            return null;
        }

        try {
            return $reader->country($ip)->country->isoCode;
        } catch (\Throwable $e) {
            return null; // address not in DB / invalid — degrade gracefully
        }
    }

    /**
     * Look up the full City record once per IP (memoized within the request).
     * Returns null on a country-only database, a missing IP, or any failure.
     */
    private function cityRecord(?string $ip): ?\GeoIp2\Model\City
    {
        if ($ip === $this->cityIp) {
            return $this->cityRec;
        }
        $this->cityIp = $ip;
        $this->cityRec = null;

        if (! $ip || $ip === '127.0.0.1' || $ip === '::1') {
            return null;
        }
        $reader = $this->reader();
        if (! $reader) {
            return null;
        }

        try {
            $this->cityRec = $reader->city($ip);
        } catch (\Throwable $e) {
            $this->cityRec = null; // country-only DB / not found / invalid
        }

        return $this->cityRec;
    }

    /** Trim a place name, drop empties, and cap to the column width. */
    private function clean(?string $value, int $max): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    private function reader(): ?Reader
    {
        if ($this->readerResolved) {
            return $this->reader;
        }
        $this->readerResolved = true;

        $path = $this->databasePath();
        if ($path) {
            try {
                $this->reader = new Reader($path);
            } catch (\Throwable $e) {
                $this->reader = null;
            }
        }

        return $this->reader;
    }

    private function databasePath(): ?string
    {
        $path = (string) config('linkforge.geo.db_path');
        if ($path !== '') {
            // Resolve relative paths from the project root.
            $isAbsolute = str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path);
            if (! $isAbsolute) {
                $path = base_path($path);
            }
            if (is_file($path)) {
                return $path;
            }
        }

        // Zero-config fallback: any .mmdb dropped into storage/app/geoip/ is used
        // automatically, so an operator can enable geo without editing .env.
        return (glob(storage_path('app/geoip/*.mmdb')) ?: [])[0] ?? null;
    }
}
