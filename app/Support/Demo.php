<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Demo-mode helper. Demo mode turns the app into a safe public showcase: visitors
 * can use every feature, but destructive / config-changing actions are blocked,
 * emails are suppressed, one-click logins are offered, and a buy CTA is shown.
 *
 * Off by default and configured by env ONLY (LF_DEMO / LF_DEMO_BUY_URL) — there is
 * deliberately no admin UI for it, so a customer install never exposes or enables
 * it. The author sets LF_DEMO=true in .env on a separate demo server.
 */
class Demo
{
    /** Fixed demo accounts (recreated by `demo:reset`). */
    public const ADMIN_EMAIL = 'admin@demo.test';

    public const USER_EMAIL = 'user@demo.test';

    /**
     * Write actions blocked in demo mode (route-name prefixes). Everything else —
     * creating links, campaigns, pixels, QR codes, upgrading via the offline
     * gateway, etc. — stays usable so visitors can try the real features.
     */
    private const BLOCKED = [
        'admin.settings',      // settings (incl. mail, license, the demo toggle itself)
        'admin.updates',       // the in-app updater
        'admin.languages',     // language file editing
        'admin.users.update', 'admin.users.destroy', // don't mutate/delete accounts
        'admin.moderation',    // don't let demo admins delete other users' content
        'account.password', 'account.profile', 'account.destroy', // keep demo logins stable
        'register',            // one-click logins are the entry point; no account sprawl
        'password.email', 'password.update', // password reset (no real mail in demo)
        // Config / security actions — visible to explore, but not changeable in the demo:
        'domains',             // add / verify / delete custom domains
        'tokens',              // API tokens
        'webhooks',            // webhook endpoints (also avoids outbound abuse from the demo)
        'billing.subscribe',   // no plan changes (keeps the demo user on the showcase plan)
    ];

    public static function enabled(): bool
    {
        return (bool) config('linkforge.demo');
    }

    public static function buyUrl(): string
    {
        return (string) (config('linkforge.demo_buy_url') ?: 'https://codecanyon.net');
    }

    /** Is a write to this route blocked in demo mode? */
    public static function blocks(?string $routeName): bool
    {
        if ($routeName === null) {
            return false;
        }

        foreach (self::BLOCKED as $prefix) {
            if ($routeName === $prefix || Str::startsWith($routeName, $prefix.'.')) {
                return true;
            }
        }

        return false;
    }
}
