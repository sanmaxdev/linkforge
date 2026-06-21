<?php

/*
|--------------------------------------------------------------------------
| LinkForge branding & theme
|--------------------------------------------------------------------------
|
| Single source of truth for white-label branding. Everything here is
| customizable by the buyer/operator. At runtime these values are injected
| as CSS variables (see resources/views/partials/theme.blade.php), so the
| whole UI re-themes without touching compiled assets. A future admin
| "Appearance" screen will persist overrides to the settings table and
| merge them on top of these defaults.
|
*/

return [

    // Brand identity
    'name' => env('APP_NAME', 'LinkForge'),
    'logo' => env('LF_LOGO'), // custom logo URL; overridable from admin Appearance settings

    // Shipped version. The applied version is tracked in the `app_version` setting
    // and bumped by the in-app updater; this is the floor for a fresh install.
    'version' => '1.0.35',
    'tagline' => env('LF_TAGLINE', 'Forge links that work harder.'),
    'description' => env('LF_DESCRIPTION', 'A premium, AI-native link platform with branded domains, deep analytics, a QR studio and safety scanning, on hosting you own.'),

    // Display date format (PHP date() syntax). Operator-overridable in Settings -> General.
    'date_format' => env('LF_DATE_FORMAT', 'M j, Y'),

    // Theme tokens (overridable per install / per tenant)
    'theme' => [

        // Typography (self-hosted via @fontsource; swap to any bundled family)
        'font' => env('LF_FONT', 'Plus Jakarta Sans'),

        // Default colour scheme for visitors who haven't chosen: light | dark | system.
        'scheme' => env('LF_SCHEME', 'system'),

        // Primary brand ramp — default: emerald
        'brand' => [
            '50' => '#ecfdf5',
            '100' => '#d1fae5',
            '200' => '#a7f3d0',
            '300' => '#6ee7b7',
            '400' => '#34d399',
            '500' => '#10b981',
            '600' => '#059669',
            '700' => '#047857',
            '800' => '#065f46',
            '900' => '#064e3b',
            '950' => '#022c22',
        ],

        // Accent ramp ("forge spark") — default: amber
        'spark' => [
            '50' => '#fffbeb',
            '100' => '#fef3c7',
            '200' => '#fde68a',
            '300' => '#fcd34d',
            '400' => '#fbbf24',
            '500' => '#f59e0b',
            '600' => '#d97706',
            '700' => '#b45309',
            '800' => '#92400e',
            '900' => '#78350f',
        ],
    ],

    // Built-in theme presets the admin can switch between (extensible).
    'presets' => [
        'forge'   => ['label' => 'Forge (emerald + amber)', 'brand' => 'emerald', 'spark' => 'amber'],
        'sapphire'=> ['label' => 'Sapphire (blue + sky)',   'brand' => 'blue',    'spark' => 'sky'],
        'sunset'  => ['label' => 'Sunset (amber + rose)',   'brand' => 'amber',   'spark' => 'rose'],
        'graphite'=> ['label' => 'Graphite (slate + teal)', 'brand' => 'slate',   'spark' => 'teal'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Safety & abuse prevention
    |--------------------------------------------------------------------------
    |
    | Local checks run synchronously at create time. Threat-feed providers are
    | each opt-in and resilient (a disabled/failing provider is ignored). With
    | none enabled, links default to "safe" after the local screen passes.
    |
    */
    'safety' => [
        'blocked_domains' => [],   // operator blocklist, e.g. ['known-phish.test']
        'blocked_keywords' => [],

        'disposable_domains' => [
            'mailinator.com', 'guerrillamail.com', '10minutemail.com', 'tempmail.com',
            'temp-mail.org', 'trashmail.com', 'yopmail.com', 'getnada.com',
            'sharklasers.com', 'dispostable.com', 'maildrop.cc', 'fakeinbox.com',
        ],

        'providers' => [
            'urlhaus' => (bool) env('SAFETY_URLHAUS', false), // free, no key
            'virustotal' => env('VIRUSTOTAL_API_KEY'),
            'webrisk' => env('WEBRISK_API_KEY'),
        ],

        // Cloudflare Turnstile CAPTCHA. When unset, the captcha check is skipped
        // (honeypot + disposable-email blocking still apply).
        'turnstile' => [
            'site' => env('TURNSTILE_SITE_KEY'),
            'secret' => env('TURNSTILE_SECRET'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Geo resolution
    |--------------------------------------------------------------------------
    |
    | Country is resolved from Cloudflare's CF-IPCountry header first (free,
    | zero-DB, works behind Cloudflare). Otherwise a local MaxMind-format .mmdb
    | at this path is read via geoip2 — GeoLite2, or the no-account DB-IP /
    | IPinfo country databases. Relative paths resolve from the project root.
    |
    */
    'geo' => [
        'db_path' => env('GEOLITE_DB_PATH'),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI layer (Anthropic / Claude)
    |--------------------------------------------------------------------------
    |
    | The whole AI layer is optional and fully config-gated: with no API key
    | set, every AI surface hides itself and the app behaves exactly as before.
    | The operator owns the API spend, so the model is operator-selectable (it
    | defaults to the most capable model; switch to a cheaper one to cut cost).
    | "ask your links" never lets the model author SQL: the model only maps a
    | question onto an allowlist of metrics/dimensions/ranges that we execute.
    |
    */
    'ai' => [
        // Active provider: "openrouter" (any model, OpenAI-compatible gateway) or
        // "anthropic" (native Claude). Defaults to OpenRouter on a cheap, fast model
        // because every bundled AI task (alias ideas, a tiny intent parse, a 1-3
        // sentence narration) is simple - no need to pay flagship prices.
        'provider' => env('AI_PROVIDER', 'openrouter'),

        // Anthropic native
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('AI_MODEL', 'claude-opus-4-8'),
        'base_url' => rtrim(env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'), '/'),
        'version' => env('ANTHROPIC_VERSION', '2023-06-01'),
        'timeout' => (int) env('AI_TIMEOUT', 30),

        // OpenRouter (OpenAI-compatible gateway to any model)
        'openrouter' => [
            'key' => env('OPENROUTER_API_KEY'),
            // Cheap + capable default for the bundled tasks. Swap to any OpenRouter slug
            // (openai/gpt-4.1-mini, google/gemini-2.0-flash-001, anthropic/claude-...).
            'model' => env('OPENROUTER_MODEL', 'openai/gpt-4o-mini'),
            'base_url' => rtrim(env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'), '/'),
        ],

        // Per-action credit cost. Credits are granted per plan (PlanSeeder).
        'cost' => [
            'alias' => 1,
            'ask' => 1,
            'insight' => 1,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Billing
    |--------------------------------------------------------------------------
    |
    | The active payment gateway. "offline" applies plan changes immediately
    | (manual / bank-transfer / demo). "stripe" redirects to Stripe Checkout
    | when STRIPE_SECRET is set, otherwise it falls back to offline.
    |
    */
    'billing' => [
        'gateway' => env('BILLING_GATEWAY', 'offline'),
        'currency' => env('BILLING_CURRENCY', 'USD'),
        'stripe' => [
            'key' => env('STRIPE_KEY'),
            'secret' => env('STRIPE_SECRET'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        ],
        'paypal' => [
            'client_id' => env('PAYPAL_CLIENT_ID'),
            'secret' => env('PAYPAL_SECRET'),
            'mode' => env('PAYPAL_MODE', 'live'), // sandbox | live
        ],
        'coinpayments' => [
            'merchant_id' => env('COINPAYMENTS_MERCHANT_ID'),
            'public_key' => env('COINPAYMENTS_PUBLIC_KEY'),
            'private_key' => env('COINPAYMENTS_PRIVATE_KEY'),
            'ipn_secret' => env('COINPAYMENTS_IPN_SECRET'),
            'receive_currency' => env('COINPAYMENTS_RECEIVE_CURRENCY', 'BTC'),
        ],
        'cryptocom' => [
            'secret_key' => env('CRYPTOCOM_SECRET_KEY'),
            'webhook_secret' => env('CRYPTOCOM_WEBHOOK_SECRET'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Envato license verification
    |--------------------------------------------------------------------------
    |
    | The buyer's CodeCanyon purchase code is verified through a small relay
    | the AUTHOR hosts (it holds the Envato personal token and calls the Envato
    | API), so the secret token never ships inside the buyer's copy. Leave the
    | relay URL empty to disable verification (the installer then accepts the
    | code unverified and never blocks the site — verification always fails open).
    |
    */
    'license' => [
        // The author-hosted verification relay (holds the Envato token). Ships pointing at
        // the author's relay so every copy verifies out of the box; override per-install
        // with LF_LICENSE_RELAY, or set to '' to disable online verification.
        'relay_url' => env('LF_LICENSE_RELAY', 'https://license.sangeeth.biz'),
        'item_id' => env('LF_ENVATO_ITEM_ID', ''), // your CodeCanyon item id (optional cross-check)
    ],

    // Demo mode: public showcase. Destructive + config-changing actions are blocked,
    // emails are suppressed, one-click logins are shown and a "this is a demo" banner +
    // buy CTA appear. Env-only (no admin UI) so customer installs never expose it —
    // set LF_DEMO=true in .env on a SEPARATE demo server only. See DEMO.md.
    'demo' => env('LF_DEMO', false),
    'demo_buy_url' => env('LF_DEMO_BUY_URL', 'https://codecanyon.net'),
];
