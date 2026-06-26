<?php

namespace App\Providers;

use App\Models\Setting;
use App\Services\Auth\SocialProviders;
use App\Support\Installer;
use App\Support\ThemePalette;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

/**
 * Overlays admin-editable settings (DB) on top of config at runtime, so the
 * operator configures branding, theme and behaviour from the admin panel
 * without ever touching .env or config files. Guarded so a fresh/uninstalled
 * database (no settings table) is a no-op.
 */
class SettingsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Before the site is installed there is no database — keep the framework
        // off it so the web installer can run on a fresh cPanel upload.
        if (! Installer::isInstalled()) {
            $this->bootInstallMode();

            return;
        }

        try {
            if (! Schema::hasTable('settings')) {
                return;
            }
            $s = Setting::allCached();
        } catch (\Throwable $e) {
            return; // DB not ready (install / migrate) — fall back to config defaults.
        }

        // Branding text.
        if (! empty($s['site_name'])) {
            config(['linkforge.name' => $s['site_name'], 'app.name' => $s['site_name']]);
        }
        if (isset($s['site_tagline'])) {
            config(['linkforge.tagline' => $s['site_tagline']]);
        }
        if (isset($s['site_description'])) {
            config(['linkforge.description' => $s['site_description']]);
        }
        if (! empty($s['brand_logo'])) {
            config(['linkforge.logo' => $s['brand_logo']]);
        }

        // Appearance: a preset drives both brand + spark ramps; font is independent.
        if (! empty($s['theme_preset']) && ThemePalette::isPreset($s['theme_preset'])) {
            $ramps = ThemePalette::ramps($s['theme_preset']);
            config(['linkforge.theme.brand' => $ramps['brand'], 'linkforge.theme.spark' => $ramps['spark']]);
        }
        if (! empty($s['theme_font'])) {
            config(['linkforge.theme.font' => $s['theme_font']]);
        }
        if (! empty($s['theme_scheme'])) {
            config(['linkforge.theme.scheme' => $s['theme_scheme']]);
        }

        // Default UI language (operator-chosen). The locale middleware reads this.
        if (! empty($s['default_locale'])) {
            config(['app.locale' => $s['default_locale']]);
        }

        // Localization: operator timezone + display date format.
        if (! empty($s['app_timezone']) && in_array($s['app_timezone'], timezone_identifiers_list(), true)) {
            config(['app.timezone' => $s['app_timezone']]);
            date_default_timezone_set($s['app_timezone']);
        }
        if (! empty($s['date_format'])) {
            config(['linkforge.date_format' => $s['date_format']]);
        }

        $this->applySafety($s);
        $this->applyBilling($s);
        $this->applyAi($s);
        $this->applyMail($s);
        $this->applyAuth($s);
    }

    /**
     * Config the framework needs to operate before a database exists: file-based
     * session/cache, synchronous queue, and an APP_KEY (sessions + CSRF require
     * it on the very first installer request).
     */
    private function bootInstallMode(): void
    {
        config([
            'session.driver' => 'file',
            'cache.default' => 'file',
            'queue.default' => 'sync',
        ]);

        if (empty(config('app.key'))) {
            $key = Installer::generateAppKey();
            config(['app.key' => $key]);
            try {
                Installer::writeEnv(['APP_KEY' => $key]);
            } catch (\Throwable $e) {
                // .env not writable yet — the requirements screen will flag it.
            }
        }
    }

    /** @param array<string,string> $s Social login (Google / GitHub / Facebook OAuth). */
    private function applyAuth(array $s): void
    {
        foreach (SocialProviders::keys() as $provider) {
            if (isset($s["{$provider}_login_enabled"])) {
                config(["services.{$provider}.enabled" => $s["{$provider}_login_enabled"] === '1']);
            }
            if (! empty($s["{$provider}_client_id"])) {
                config(["services.{$provider}.client_id" => $s["{$provider}_client_id"]]);
            }
            if (! empty($s["{$provider}_client_secret"])) {
                config(["services.{$provider}.client_secret" => $s["{$provider}_client_secret"]]);
            }
        }
    }

    /** @param array<string,string> $s */
    private function applySafety(array $s): void
    {
        if (isset($s['safety_blocked_domains'])) {
            config(['linkforge.safety.blocked_domains' => $this->lines($s['safety_blocked_domains'])]);
        }
        if (isset($s['safety_blocked_keywords'])) {
            config(['linkforge.safety.blocked_keywords' => $this->lines($s['safety_blocked_keywords'])]);
        }
        if (isset($s['safety_urlhaus'])) {
            config(['linkforge.safety.providers.urlhaus' => $s['safety_urlhaus'] === '1']);
        }
        if (! empty($s['safety_virustotal_key'])) {
            config(['linkforge.safety.providers.virustotal' => $s['safety_virustotal_key']]);
        }
        if (! empty($s['safety_webrisk_key'])) {
            config(['linkforge.safety.providers.webrisk' => $s['safety_webrisk_key']]);
        }
        if (! empty($s['turnstile_site'])) {
            config(['linkforge.safety.turnstile.site' => $s['turnstile_site']]);
        }
        if (! empty($s['turnstile_secret'])) {
            config(['linkforge.safety.turnstile.secret' => $s['turnstile_secret']]);
        }
    }

    /** @param array<string,string> $s */
    private function applyBilling(array $s): void
    {
        if (! empty($s['billing_gateway'])) {
            config(['linkforge.billing.gateway' => $s['billing_gateway']]);
        }
        if (! empty($s['billing_currency'])) {
            config(['linkforge.billing.currency' => $s['billing_currency']]);
        }
        if (! empty($s['stripe_key'])) {
            config(['linkforge.billing.stripe.key' => $s['stripe_key']]);
        }
        if (! empty($s['stripe_secret'])) {
            config(['linkforge.billing.stripe.secret' => $s['stripe_secret']]);
        }
        if (! empty($s['stripe_webhook_secret'])) {
            config(['linkforge.billing.stripe.webhook_secret' => $s['stripe_webhook_secret']]);
        }

        // PayPal
        if (! empty($s['paypal_client_id'])) {
            config(['linkforge.billing.paypal.client_id' => $s['paypal_client_id']]);
        }
        if (! empty($s['paypal_secret'])) {
            config(['linkforge.billing.paypal.secret' => $s['paypal_secret']]);
        }
        if (! empty($s['paypal_mode'])) {
            config(['linkforge.billing.paypal.mode' => $s['paypal_mode']]);
        }

        // CoinPayments
        foreach (['merchant_id', 'public_key', 'private_key', 'ipn_secret', 'receive_currency'] as $k) {
            if (! empty($s["coinpayments_{$k}"])) {
                config(["linkforge.billing.coinpayments.{$k}" => $s["coinpayments_{$k}"]]);
            }
        }

        // Crypto.com Pay
        if (! empty($s['cryptocom_secret_key'])) {
            config(['linkforge.billing.cryptocom.secret_key' => $s['cryptocom_secret_key']]);
        }
        if (! empty($s['cryptocom_webhook_secret'])) {
            config(['linkforge.billing.cryptocom.webhook_secret' => $s['cryptocom_webhook_secret']]);
        }
    }

    /** @param array<string,string> $s */
    private function applyAi(array $s): void
    {
        if (! empty($s['ai_provider'])) {
            config(['linkforge.ai.provider' => $s['ai_provider']]);
        }
        if (! empty($s['ai_key'])) {
            config(['linkforge.ai.key' => $s['ai_key']]);
        }
        if (! empty($s['ai_model'])) {
            config(['linkforge.ai.model' => $s['ai_model']]);
        }
        if (! empty($s['openrouter_key'])) {
            config(['linkforge.ai.openrouter.key' => $s['openrouter_key']]);
        }
        if (! empty($s['openrouter_model'])) {
            config(['linkforge.ai.openrouter.model' => $s['openrouter_model']]);
        }
        foreach (['alias', 'ask', 'insight'] as $action) {
            if (isset($s["ai_cost_{$action}"])) {
                config(["linkforge.ai.cost.{$action}" => (int) $s["ai_cost_{$action}"]]);
            }
        }
    }

    /** @param array<string,string> $s */
    private function applyMail(array $s): void
    {
        if (empty($s['mail_host'])) {
            return; // no SMTP configured — keep the env/config default mailer
        }

        $scheme = ($s['mail_encryption'] ?? 'tls') === 'ssl' ? 'smtps' : null;

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $s['mail_host'],
            'mail.mailers.smtp.port' => (int) ($s['mail_port'] ?? 587),
            'mail.mailers.smtp.username' => $s['mail_username'] ?? null,
            'mail.mailers.smtp.scheme' => $scheme,
        ]);
        if (! empty($s['mail_password'])) {
            config(['mail.mailers.smtp.password' => $s['mail_password']]);
        }
        if (! empty($s['mail_from_address'])) {
            config(['mail.from.address' => $s['mail_from_address']]);
        }
        if (! empty($s['mail_from_name'])) {
            config(['mail.from.name' => $s['mail_from_name']]);
        }
    }

    /** Split a textarea blob into a trimmed, non-empty list. */
    private function lines(string $blob): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $blob)), fn ($v) => $v !== ''));
    }
}
