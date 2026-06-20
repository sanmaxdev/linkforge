<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\EmailTemplate;
use App\Models\Setting;
use App\Services\Analytics\GeoipUpdater;
use App\Support\EmailEvents;
use App\Support\ThemePalette;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SettingController extends Controller
{
    /** Tabs in display order: key => label. */
    public const TABS = [
        'general' => 'General',
        'appearance' => 'Appearance',
        'login' => 'Social login',
        'safety' => 'Safety',
        'billing' => 'Billing',
        'ai' => 'AI',
        'email' => 'Email',
        'geo' => 'Geo',
        'domains' => 'Domains',
        'seo' => 'SEO',
    ];

    public const GATEWAYS = [
        'offline' => 'Offline / manual',
        'stripe' => 'Stripe',
        'paypal' => 'PayPal',
        'coinpayments' => 'CoinPayments (crypto)',
        'cryptocom' => 'Crypto.com Pay',
    ];

    public const MAIL_ENCRYPTION = ['none' => 'None', 'tls' => 'TLS (STARTTLS, port 587)', 'ssl' => 'SSL (port 465)'];

    public const AI_PROVIDERS = ['anthropic' => 'Anthropic (Claude)', 'openrouter' => 'OpenRouter (any model)'];

    /** Secret keys are never echoed back; only updated when a new value is submitted. */
    public const SECRETS = [
        'safety_virustotal_key', 'safety_webrisk_key', 'turnstile_secret',
        'stripe_secret', 'stripe_webhook_secret', 'ai_key', 'mail_password',
        'paypal_secret', 'coinpayments_private_key', 'coinpayments_ipn_secret',
        'cryptocom_secret_key', 'cryptocom_webhook_secret', 'openrouter_key',
        'google_client_secret', 'geoip_maxmind_key',
    ];

    public function index(Request $request)
    {
        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'general';
        $s = Setting::allCached();

        return view('admin.settings.index', [
            'tab' => $tab,
            'tabs' => self::TABS,
            's' => $s,
            'secretsSet' => collect(self::SECRETS)->mapWithKeys(fn ($k) => [$k => ! empty($s[$k])])->all(),
            'presets' => ThemePalette::PRESETS,
            'colors' => ThemePalette::COLORS,
            'fonts' => ThemePalette::FONTS,
            'gateways' => self::GATEWAYS,
            'encryptions' => self::MAIL_ENCRYPTION,
            'aiProviders' => self::AI_PROVIDERS,
            'emailEvents' => EmailEvents::EVENTS,
            'emailTemplates' => collect(array_keys(EmailEvents::EVENTS))->mapWithKeys(fn ($k) => [$k => EmailTemplate::resolve($k)])->all(),
            'geoProviders' => GeoipUpdater::PROVIDERS,
            'geoEditions' => GeoipUpdater::EDITIONS,
            'geoDetected' => $this->geoDatabasePresent(),
            'appHost' => parse_url((string) config('app.url'), PHP_URL_HOST) ?: $request->getHost(),
            'autoServerIp' => $request->server('SERVER_ADDR'),
            'docRoot' => public_path(),
        ]);
    }

    /** Whether a GeoIP database is available (operator upload, auto-update, or the bundled seed). */
    private function geoDatabasePresent(): bool
    {
        return ! empty(glob(storage_path('app/geoip/*.mmdb'))) || ! empty(glob(base_path('database/geoip/*.mmdb')));
    }

    public function update(Request $request)
    {
        return match ($request->input('section')) {
            'general' => $this->saveGeneral($request),
            'appearance' => $this->saveAppearance($request),
            'login' => $this->saveLogin($request),
            'safety' => $this->saveSafety($request),
            'billing' => $this->saveBilling($request),
            'ai' => $this->saveAi($request),
            'email' => $this->saveEmail($request),
            'email_template' => $this->saveEmailTemplate($request),
            'geo' => $this->saveGeo($request),
            'domains' => $this->saveDomains($request),
            'seo' => $this->saveSeo($request),
            default => back()->with('error', 'Unknown settings section.'),
        };
    }

    private function saveEmailTemplate(Request $request)
    {
        $event = (string) $request->input('event');
        if (! array_key_exists($event, EmailEvents::EVENTS)) {
            return back()->with('error', 'Unknown email event.');
        }

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:8000'],
        ]);

        EmailTemplate::updateOrCreate(['event' => $event], [
            'subject' => $data['subject'],
            'body' => $data['body'],
            'enabled' => $request->boolean('enabled'),
        ]);
        AuditLog::record('email.template', 'Updated email template: '.EmailEvents::EVENTS[$event]['label']);

        return redirect()->route('admin.settings', ['tab' => 'email'])->with('status', EmailEvents::EVENTS[$event]['label'].' email saved.');
    }

    private function saveGeneral(Request $request)
    {
        $data = $request->validate([
            'site_name' => ['nullable', 'string', 'max:120'],
            'site_tagline' => ['nullable', 'string', 'max:200'],
            'site_description' => ['nullable', 'string', 'max:500'],
            'maintenance_message' => ['nullable', 'string', 'max:300'],
        ]);

        Setting::putMany([
            'site_name' => $data['site_name'] ?: config('linkforge.name'),
            'site_tagline' => (string) ($data['site_tagline'] ?? ''),
            'site_description' => (string) ($data['site_description'] ?? ''),
            'allow_registration' => $request->boolean('allow_registration') ? '1' : '0',
            'maintenance_mode' => $request->boolean('maintenance_mode') ? '1' : '0',
            'maintenance_message' => (string) ($data['maintenance_message'] ?? ''),
        ]);

        return $this->done('general', 'General settings saved.');
    }

    private function saveAppearance(Request $request)
    {
        $data = $request->validate([
            'theme_preset' => ['required', Rule::in(array_keys(ThemePalette::PRESETS))],
            'theme_font' => ['required', Rule::in(ThemePalette::FONTS)],
            'theme_scheme' => ['nullable', Rule::in(['light', 'dark', 'system'])],
            'logo_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif,svg', 'max:2048'],
        ]);

        $out = ['theme_preset' => $data['theme_preset'], 'theme_font' => $data['theme_font'], 'theme_scheme' => $data['theme_scheme'] ?? 'system'];

        if ($request->hasFile('logo_file')) {
            $out['brand_logo'] = $this->storeLogo($request->file('logo_file'));
        } elseif ($request->boolean('logo_clear')) {
            $out['brand_logo'] = '';
        }

        Setting::putMany($out);

        return $this->done('appearance', 'Appearance saved.');
    }

    /** Store an uploaded logo (raster auto-downscaled to 256px; SVG kept as-is) and return its public URL. */
    private function storeLogo(\Illuminate\Http\UploadedFile $file): string
    {
        $dir = public_path('uploads/branding');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $base = 'logo-'.\Illuminate\Support\Str::random(10);

        if (strtolower($file->getClientOriginalExtension()) === 'svg') {
            $file->move($dir, $base.'.svg');

            return asset('uploads/branding/'.$base.'.svg');
        }

        $dest = $dir.DIRECTORY_SEPARATOR.$base.'.png';
        if (\App\Support\ImageResizer::fitToPng($file->getPathname(), $dest, 256)) {
            return asset('uploads/branding/'.$base.'.png');
        }

        // Resize failed (unreadable): keep the original.
        $file->move($dir, $base.'.'.$file->getClientOriginalExtension());

        return asset('uploads/branding/'.$base.'.'.$file->getClientOriginalExtension());
    }

    private function saveLogin(Request $request)
    {
        $data = $request->validate([
            'google_client_id' => ['nullable', 'string', 'max:255'],
        ]);

        $out = [
            'google_login_enabled' => $request->boolean('google_login_enabled') ? '1' : '0',
            'google_client_id' => (string) ($data['google_client_id'] ?? ''),
        ];
        $this->applySecret($out, $request, 'google_client_secret');
        Setting::putMany($out);

        return $this->done('login', 'Social login settings saved.');
    }

    private function saveSafety(Request $request)
    {
        $data = $request->validate([
            'safety_blocked_domains' => ['nullable', 'string', 'max:20000'],
            'safety_blocked_keywords' => ['nullable', 'string', 'max:20000'],
            'turnstile_site' => ['nullable', 'string', 'max:120'],
        ]);

        $out = [
            'safety_blocked_domains' => (string) ($data['safety_blocked_domains'] ?? ''),
            'safety_blocked_keywords' => (string) ($data['safety_blocked_keywords'] ?? ''),
            'safety_urlhaus' => $request->boolean('safety_urlhaus') ? '1' : '0',
            'turnstile_site' => (string) ($data['turnstile_site'] ?? ''),
        ];
        $this->applySecret($out, $request, 'safety_virustotal_key');
        $this->applySecret($out, $request, 'safety_webrisk_key');
        $this->applySecret($out, $request, 'turnstile_secret');
        Setting::putMany($out);

        return $this->done('safety', 'Safety settings saved.');
    }

    private function saveBilling(Request $request)
    {
        $data = $request->validate([
            'billing_gateway' => ['required', Rule::in(array_keys(self::GATEWAYS))],
            'billing_currency' => ['required', 'string', 'size:3'],
            'stripe_key' => ['nullable', 'string', 'max:255'],
            'paypal_client_id' => ['nullable', 'string', 'max:255'],
            'paypal_mode' => ['required', Rule::in(['sandbox', 'live'])],
            'coinpayments_merchant_id' => ['nullable', 'string', 'max:255'],
            'coinpayments_public_key' => ['nullable', 'string', 'max:255'],
            'coinpayments_receive_currency' => ['nullable', 'string', 'max:10'],
        ]);

        $out = [
            'billing_gateway' => $data['billing_gateway'],
            'billing_currency' => strtoupper($data['billing_currency']),
            'stripe_key' => (string) ($data['stripe_key'] ?? ''),
            'paypal_client_id' => (string) ($data['paypal_client_id'] ?? ''),
            'paypal_mode' => $data['paypal_mode'],
            'coinpayments_merchant_id' => (string) ($data['coinpayments_merchant_id'] ?? ''),
            'coinpayments_public_key' => (string) ($data['coinpayments_public_key'] ?? ''),
            'coinpayments_receive_currency' => strtoupper((string) ($data['coinpayments_receive_currency'] ?: 'BTC')),
        ];
        foreach (['stripe_secret', 'stripe_webhook_secret', 'paypal_secret', 'coinpayments_private_key', 'coinpayments_ipn_secret', 'cryptocom_secret_key', 'cryptocom_webhook_secret'] as $secret) {
            $this->applySecret($out, $request, $secret);
        }
        Setting::putMany($out);

        return $this->done('billing', 'Billing settings saved.');
    }

    private function saveAi(Request $request)
    {
        $data = $request->validate([
            'ai_provider' => ['required', Rule::in(array_keys(self::AI_PROVIDERS))],
            'ai_model' => ['nullable', 'string', 'max:120'],
            'openrouter_model' => ['nullable', 'string', 'max:120'],
            'ai_cost_alias' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'ai_cost_ask' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'ai_cost_insight' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ]);

        $out = [
            'ai_provider' => $data['ai_provider'],
            'ai_model' => ($data['ai_model'] ?? '') ?: config('linkforge.ai.model'),
            'openrouter_model' => ($data['openrouter_model'] ?? '') ?: config('linkforge.ai.openrouter.model'),
            'ai_cost_alias' => (string) ((int) ($data['ai_cost_alias'] ?? 1)),
            'ai_cost_ask' => (string) ((int) ($data['ai_cost_ask'] ?? 1)),
            'ai_cost_insight' => (string) ((int) ($data['ai_cost_insight'] ?? 1)),
        ];
        $this->applySecret($out, $request, 'ai_key');
        $this->applySecret($out, $request, 'openrouter_key');
        Setting::putMany($out);

        return $this->done('ai', 'AI settings saved.');
    }

    private function saveEmail(Request $request)
    {
        $data = $request->validate([
            'mail_host' => ['nullable', 'string', 'max:190'],
            'mail_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'mail_username' => ['nullable', 'string', 'max:190'],
            'mail_encryption' => ['required', Rule::in(array_keys(self::MAIL_ENCRYPTION))],
            'mail_from_address' => ['nullable', 'email', 'max:190'],
            'mail_from_name' => ['nullable', 'string', 'max:120'],
        ]);

        $out = [
            'mail_host' => (string) ($data['mail_host'] ?? ''),
            'mail_port' => (string) ($data['mail_port'] ?? ''),
            'mail_username' => (string) ($data['mail_username'] ?? ''),
            'mail_encryption' => $data['mail_encryption'],
            'mail_from_address' => (string) ($data['mail_from_address'] ?? ''),
            'mail_from_name' => (string) ($data['mail_from_name'] ?? ''),
        ];
        $this->applySecret($out, $request, 'mail_password');
        Setting::putMany($out);

        return $this->done('email', 'Email settings saved.');
    }

    private function saveSeo(Request $request)
    {
        $data = $request->validate([
            'seo_meta_description' => ['nullable', 'string', 'max:300'],
            'seo_og_title' => ['nullable', 'string', 'max:120'],
            'seo_og_description' => ['nullable', 'string', 'max:300'],
            'seo_twitter_handle' => ['nullable', 'string', 'max:40', 'regex:/^@?[A-Za-z0-9_]*$/'],
            'seo_ga_id' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9\-]*$/'],
            'seo_gtm_id' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9\-]*$/'],
            'seo_og_image_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048'],
        ]);

        $handle = trim((string) ($data['seo_twitter_handle'] ?? ''));

        $out = [
            'seo_meta_description' => (string) ($data['seo_meta_description'] ?? ''),
            'seo_og_title' => (string) ($data['seo_og_title'] ?? ''),
            'seo_og_description' => (string) ($data['seo_og_description'] ?? ''),
            'seo_twitter_handle' => $handle !== '' ? '@'.ltrim($handle, '@') : '',
            'seo_ga_id' => (string) ($data['seo_ga_id'] ?? ''),
            'seo_gtm_id' => (string) ($data['seo_gtm_id'] ?? ''),
        ];

        if ($request->hasFile('seo_og_image_file')) {
            $out['seo_og_image'] = $this->storeSocialImage($request->file('seo_og_image_file'));
        } elseif ($request->boolean('seo_og_image_clear')) {
            $out['seo_og_image'] = '';
        }

        Setting::putMany($out);

        return $this->done('seo', 'SEO settings saved.');
    }

    /** Store an uploaded social/OG image as-is (no downscale) and return its public URL. */
    private function storeSocialImage(\Illuminate\Http\UploadedFile $file): string
    {
        $dir = public_path('uploads/branding');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $ext = strtolower((string) $file->guessExtension());
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            $ext = 'png';
        }
        $name = 'og-'.\Illuminate\Support\Str::random(10).'.'.$ext;
        $file->move($dir, $name);

        return asset('uploads/branding/'.$name);
    }

    private function saveDomains(Request $request)
    {
        $data = $request->validate([
            'custom_domain_target' => ['nullable', 'string', 'max:190', 'regex:/^[A-Za-z0-9.\-]+\.[A-Za-z]{2,}$/'],
            'custom_domain_ip' => ['nullable', 'string', 'max:45', 'ip'],
        ]);

        Setting::putMany([
            'custom_domain_target' => strtolower((string) ($data['custom_domain_target'] ?? '')),
            'custom_domain_ip' => (string) ($data['custom_domain_ip'] ?? ''),
        ]);

        return $this->done('domains', 'Domain settings saved.');
    }

    private function saveGeo(Request $request)
    {
        $data = $request->validate([
            'geoip_provider' => ['required', Rule::in(array_keys(GeoipUpdater::PROVIDERS))],
            'geoip_edition' => ['required', Rule::in(array_keys(GeoipUpdater::EDITIONS))],
        ]);

        $out = [
            'geoip_provider' => $data['geoip_provider'],
            'geoip_edition' => $data['geoip_edition'],
            'geo_cf_headers' => $request->boolean('geo_cf_headers') ? '1' : '0',
        ];
        $this->applySecret($out, $request, 'geoip_maxmind_key');
        Setting::putMany($out);

        return $this->done('geo', 'Geo settings saved. Use "Download / update database" to fetch it now.');
    }

    /**
     * Download / refresh the GeoIP database on demand — no-JS fallback (the form
     * posts here when JavaScript is unavailable). The browser-driven path uses the
     * chunked endpoints below so the large City database survives host timeouts.
     */
    public function updateGeoDatabase(GeoipUpdater $updater)
    {
        @set_time_limit(0); // the City database can take a minute to download + decompress

        try {
            $message = $updater->update(Setting::get('geoip_provider', 'dbip'), Setting::get('geoip_edition', 'country'));
            AuditLog::record('geoip.update', $message);

            return redirect()->route('admin.settings', ['tab' => 'geo'])->with('status', $message);
        } catch (\Throwable $e) {
            return redirect()->route('admin.settings', ['tab' => 'geo'])->with('error', 'GeoIP update failed: '.$e->getMessage());
        }
    }

    /**
     * Begin a chunked GeoIP download (AJAX). Falls back to a one-shot install when
     * the source has no HTTP Range support (or for MaxMind), reporting that as
     * {finished:true} so the client just reloads.
     */
    public function geoDownloadStart(GeoipUpdater $updater)
    {
        @set_time_limit(0);

        try {
            $provider = (string) Setting::get('geoip_provider', 'dbip');
            $edition = (string) Setting::get('geoip_edition', 'country');

            $begin = $updater->beginChunkedDownload($provider, $edition);
            if (empty($begin['chunked'])) {
                $message = $updater->update($provider, $edition);
                AuditLog::record('geoip.update', $message);

                return response()->json(['finished' => true, 'message' => $message]);
            }

            return response()->json([
                'chunked' => true,
                'total' => $begin['total'],
                'received' => $begin['received'] ?? 0,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /** Download the next chunk (AJAX). */
    public function geoDownloadChunk(GeoipUpdater $updater)
    {
        @set_time_limit(0);

        try {
            return response()->json($updater->pullChunk());
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /** Decompress + install the completed download (AJAX). */
    public function geoDownloadFinish(GeoipUpdater $updater)
    {
        @set_time_limit(0);

        try {
            $message = $updater->finishChunkedDownload();
            AuditLog::record('geoip.update', $message);

            return response()->json(['finished' => true, 'message' => $message]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Conditionally stage a secret value: update only when a new value is typed,
     * clear when the "_clear" toggle is set, otherwise leave the stored value intact.
     */
    private function applySecret(array &$out, Request $request, string $field): void
    {
        if ($request->filled($field)) {
            $out[$field] = (string) $request->input($field);
        } elseif ($request->boolean($field.'_clear')) {
            $out[$field] = '';
        }
    }

    private function done(string $tab, string $message)
    {
        \App\Models\AuditLog::record('settings.update', ucfirst($tab).' settings updated');

        return redirect()->route('admin.settings', ['tab' => $tab])->with('status', $message);
    }
}
