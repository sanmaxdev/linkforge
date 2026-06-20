<?php

namespace App\Services\Linking;

use App\Models\Link;
use App\Models\Setting;

class AliasGenerator
{
    /** Base62 minus visually ambiguous characters (0/O, 1/l/I). */
    private const ALPHABET = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /**
     * First-party top-level paths a short link must never claim, so an alias can
     * never shadow a real route. "docs" is the important one (it serves the
     * documentation); the rest mirror the app's own route prefixes.
     */
    private const BASELINE_RESERVED = [
        'docs', 'admin', 'api', 'app', 'dashboard', 'login', 'logout', 'register',
        'password', 'install', 'blog', 'help', 'report', 'billing', 'account', 'b',
        'ref', 'shorten', 'unlock', 'auth', 'links', 'campaigns', 'qr', 'bio',
        'pixels', 'domains', 'developer', 'monetization', 'affiliate', 'demo',
    ];

    /** Generate a unique random alias for the given domain. */
    public function generate(int $domainId, int $length = 6): string
    {
        do {
            $alias = $this->random($length);
        } while ($this->isReserved($alias) || $this->taken($domainId, $alias));

        return $alias;
    }

    public function random(int $length = 6): string
    {
        $max = strlen(self::ALPHABET) - 1;
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= self::ALPHABET[random_int(0, $max)];
        }

        return $out;
    }

    public function taken(int $domainId, string $alias): bool
    {
        return Link::where('domain_id', $domainId)->where('alias', $alias)->exists();
    }

    public function isReserved(string $alias): bool
    {
        return in_array(strtolower($alias), $this->reserved(), true);
    }

    /** @return list<string> */
    public function reserved(): array
    {
        $raw = Setting::get('reserved_aliases');
        $list = $raw ? json_decode($raw, true) : [];
        $list = is_array($list) ? $list : [];

        return array_map('strtolower', array_merge(self::BASELINE_RESERVED, $list));
    }

    /**
     * Validate a user-supplied custom alias.
     *
     * @return string|null  Error message, or null if valid.
     */
    public function validateCustom(string $alias, int $domainId, ?int $ignoreLinkId = null): ?string
    {
        if (! preg_match('/^[A-Za-z0-9\-_]{1,190}$/', $alias)) {
            return 'Use only letters, numbers, hyphens and underscores.';
        }

        if ($this->isReserved($alias)) {
            return 'That alias is reserved. Please choose another.';
        }

        $exists = Link::where('domain_id', $domainId)
            ->where('alias', $alias)
            ->when($ignoreLinkId, fn ($q) => $q->where('id', '!=', $ignoreLinkId))
            ->exists();

        if ($exists) {
            return 'That alias is already taken.';
        }

        return null;
    }
}
