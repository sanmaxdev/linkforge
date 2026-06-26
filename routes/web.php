<?php

use App\Http\Controllers\AbuseReportController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AdvertisementController;
use App\Http\Controllers\Admin\BillingController as AdminBillingController;
use App\Http\Controllers\Admin\BroadcastController;
use App\Http\Controllers\Admin\HelpArticleController;
use App\Http\Controllers\Admin\LanguageController;
use App\Http\Controllers\Admin\ModerationController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\PostController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\TicketController as AdminTicketController;
use App\Http\Controllers\Admin\UpdateController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AffiliateController;
use App\Http\Controllers\AiController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\ApiTokenController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\BioController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\BulkLinkController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DemoController;
use App\Http\Controllers\DeveloperController;
use App\Http\Controllers\DocsController;
use App\Http\Controllers\DomainController;
use App\Http\Controllers\GuestShortenController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\Install\InstallController;
use App\Http\Controllers\LinkController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MonetizationController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PixelController;
use App\Http\Controllers\QrController;
use App\Http\Controllers\RedirectController;
use App\Http\Controllers\ReferralController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\WebhookController;
use App\Services\Auth\SocialProviders;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

// First-run web installer (sealed off once the site is installed).
Route::middleware('install.guard')->prefix('install')->name('install.')->group(function () {
    Route::get('/', [InstallController::class, 'welcome'])->name('welcome');
    Route::get('/database', [InstallController::class, 'database'])->name('database');
    Route::post('/database', [InstallController::class, 'saveDatabase'])->name('database.save');
    Route::get('/account', [InstallController::class, 'account'])->name('account');
    Route::post('/account', [InstallController::class, 'saveAccount'])->name('account.save');
    Route::get('/complete', [InstallController::class, 'complete'])->name('complete');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/links', [LinkController::class, 'index'])->name('links.index');
    Route::get('/links/create', [LinkController::class, 'create'])->name('links.create');
    Route::get('/links/bulk', [BulkLinkController::class, 'create'])->name('links.bulk');
    Route::post('/links/bulk', [BulkLinkController::class, 'store'])->middleware('throttle:10,1')->name('links.bulk.store');
    Route::post('/links/import', [BulkLinkController::class, 'import'])->middleware('throttle:10,1')->name('links.import');
    Route::post('/links', [LinkController::class, 'store'])->middleware('throttle:30,1')->name('links.store');
    Route::get('/links/{link}/edit', [LinkController::class, 'edit'])->name('links.edit');
    Route::put('/links/{link}', [LinkController::class, 'update'])->name('links.update');
    Route::delete('/links/{link}', [LinkController::class, 'destroy'])->name('links.destroy');
    // NB: avoid the URL segment "stats" — cPanel/ModSecurity reserve & 403 it. Route names kept for back-compat.
    Route::get('/links/{link}/analytics', [AnalyticsController::class, 'show'])->name('links.stats');
    Route::get('/links/{link}/analytics/export', [AnalyticsController::class, 'exportLink'])->name('links.stats.export');

    Route::get('/campaigns', [CampaignController::class, 'index'])->name('campaigns.index');
    Route::post('/campaigns', [CampaignController::class, 'store'])->name('campaigns.store');
    Route::put('/campaigns/{campaign}', [CampaignController::class, 'update'])->name('campaigns.update');
    Route::delete('/campaigns/{campaign}', [CampaignController::class, 'destroy'])->name('campaigns.destroy');
    Route::get('/campaigns/{campaign}/analytics', [AnalyticsController::class, 'campaignShow'])->name('campaigns.stats');
    Route::get('/campaigns/{campaign}/analytics/export', [AnalyticsController::class, 'exportCampaign'])->name('campaigns.stats.export');

    Route::get('/affiliate', [AffiliateController::class, 'index'])->name('affiliate.index');
    Route::post('/affiliate/payout', [AffiliateController::class, 'payout'])->middleware('throttle:6,1')->name('affiliate.payout');

    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
    Route::get('/analytics/export', [AnalyticsController::class, 'export'])->name('analytics.export');

    Route::get('/links/{link}/qr', [QrController::class, 'show'])->name('links.qr');
    Route::get('/links/{link}/qr/render', [QrController::class, 'render'])->name('links.qr.render');

    // Standalone QR studio (content types + full styling).
    Route::get('/qr', [QrController::class, 'index'])->name('qr.index');
    Route::get('/qr/create', [QrController::class, 'create'])->name('qr.create');
    Route::post('/qr', [QrController::class, 'store'])->name('qr.store');
    Route::post('/qr/templates', [QrController::class, 'storeTemplate'])->name('qr.templates.store');
    Route::delete('/qr/templates/{template}', [QrController::class, 'destroyTemplate'])->name('qr.templates.destroy');
    Route::get('/qr/bulk', [QrController::class, 'bulkForm'])->name('qr.bulk');
    Route::post('/qr/bulk', [QrController::class, 'bulkStore'])->name('qr.bulk.store');
    Route::get('/qr/{qr}/analytics', [AnalyticsController::class, 'qrShow'])->name('qr.stats');
    Route::get('/qr/{qr}/analytics/export', [AnalyticsController::class, 'exportQr'])->name('qr.stats.export');
    Route::get('/qr/{qr}/edit', [QrController::class, 'edit'])->name('qr.edit');
    Route::put('/qr/{qr}', [QrController::class, 'update'])->name('qr.update');
    Route::delete('/qr/{qr}', [QrController::class, 'destroy'])->name('qr.destroy');

    Route::get('/pixels', [PixelController::class, 'index'])->name('pixels.index');
    Route::post('/pixels', [PixelController::class, 'store'])->name('pixels.store');
    Route::delete('/pixels/{pixel}', [PixelController::class, 'destroy'])->name('pixels.destroy');

    Route::get('/bio', [BioController::class, 'index'])->name('bio.index');
    Route::get('/bio/create', [BioController::class, 'create'])->name('bio.create');
    Route::post('/bio', [BioController::class, 'store'])->name('bio.store');
    Route::post('/bio/preview', [BioController::class, 'preview'])->middleware('throttle:120,1')->name('bio.preview');
    Route::post('/bio/upload', [BioController::class, 'upload'])->middleware('throttle:60,1')->name('bio.upload');
    Route::post('/bio/upload-file', [BioController::class, 'uploadFile'])->middleware('throttle:60,1')->name('bio.upload-file');
    Route::get('/bio/{bioPage}/edit', [BioController::class, 'edit'])->name('bio.edit');
    Route::get('/bio/{bioPage}/analytics', [AnalyticsController::class, 'bioShow'])->name('bio.stats');
    Route::get('/bio/{bioPage}/analytics/export', [AnalyticsController::class, 'exportBio'])->name('bio.stats.export');
    Route::get('/bio/{bioPage}/leads', [BioController::class, 'leads'])->name('bio.leads');
    Route::get('/bio/{bioPage}/leads/export', [BioController::class, 'exportLeads'])->name('bio.leads.export');
    Route::put('/bio/{bioPage}', [BioController::class, 'update'])->name('bio.update');
    Route::delete('/bio/{bioPage}', [BioController::class, 'destroy'])->name('bio.destroy');

    Route::get('/domains', [DomainController::class, 'index'])->name('domains.index');
    Route::post('/domains', [DomainController::class, 'store'])->name('domains.store');
    Route::post('/domains/{domain}/verify', [DomainController::class, 'verify'])->name('domains.verify');
    Route::delete('/domains/{domain}', [DomainController::class, 'destroy'])->name('domains.destroy');

    Route::get('/monetization', [MonetizationController::class, 'index'])->name('monetization.index');
    Route::put('/monetization', [MonetizationController::class, 'update'])->name('monetization.update');

    Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
    Route::post('/billing/{plan}/subscribe', [BillingController::class, 'subscribe'])->name('billing.subscribe');
    Route::get('/billing/return/{gateway}', [BillingController::class, 'return'])->name('billing.return');

    // Developer hub: API tokens + webhooks under one tabbed page.
    Route::get('/developer', [DeveloperController::class, 'index'])->name('developer.index');

    // API tokens (create/revoke); the standalone index now redirects into the hub.
    Route::get('/api-tokens', fn () => redirect()->route('developer.index', ['tab' => 'tokens']))->name('tokens.index');
    Route::post('/api-tokens', [ApiTokenController::class, 'store'])->name('tokens.store');
    Route::delete('/api-tokens/{token}', [ApiTokenController::class, 'destroy'])->name('tokens.destroy');

    // Webhooks (create/remove); the standalone index now redirects into the hub.
    Route::get('/webhooks', fn () => redirect()->route('developer.index', ['tab' => 'webhooks']))->name('webhooks.index');
    Route::post('/webhooks', [WebhookController::class, 'store'])->name('webhooks.store');
    Route::delete('/webhooks/{webhook}', [WebhookController::class, 'destroy'])->name('webhooks.destroy');

    // Support tickets (customer side).
    Route::get('/support', [SupportController::class, 'index'])->name('support.index');
    Route::get('/support/create', [SupportController::class, 'create'])->name('support.create');
    Route::post('/support', [SupportController::class, 'store'])->middleware('throttle:20,1')->name('support.store');
    Route::get('/support/{ticket}', [SupportController::class, 'show'])->name('support.show');
    Route::post('/support/{ticket}/reply', [SupportController::class, 'reply'])->middleware('throttle:30,1')->name('support.reply');
    Route::post('/support/{ticket}/close', [SupportController::class, 'close'])->name('support.close');

    // AI assist (alias suggestions, "ask your links", title/bio writers, link insight).
    // All inert without an API key.
    Route::post('/ai/alias', [AiController::class, 'suggestAlias'])->middleware('throttle:20,1')->name('ai.alias');
    Route::post('/ai/ask', [AiController::class, 'ask'])->middleware('throttle:20,1')->name('ai.ask');
    Route::post('/ai/title', [AiController::class, 'writeTitle'])->middleware('throttle:20,1')->name('ai.title');
    Route::post('/ai/bio-copy', [AiController::class, 'bioCopy'])->middleware('throttle:20,1')->name('ai.bio-copy');
    Route::post('/ai/links/{link}/insight', [AiController::class, 'linkInsight'])->middleware('throttle:20,1')->name('ai.link-insight');

    // Account settings (profile, avatar, password, 2FA, account deletion).
    Route::get('/account', [AccountController::class, 'edit'])->name('account');
    Route::put('/account/profile', [AccountController::class, 'updateProfile'])->name('account.profile');
    Route::put('/account/password', [AccountController::class, 'updatePassword'])->name('account.password');
    Route::delete('/account', [AccountController::class, 'destroy'])->name('account.destroy');

    // Connected accounts (link / unlink a social provider to the signed-in user).
    foreach (SocialProviders::keys() as $socialProvider) {
        Route::get("/account/connections/{$socialProvider}", [SocialAuthController::class, 'connect'])->defaults('provider', $socialProvider)->middleware('throttle:30,1')->name("account.{$socialProvider}.connect");
        Route::delete("/account/connections/{$socialProvider}", [SocialAuthController::class, 'disconnect'])->defaults('provider', $socialProvider)->name("account.{$socialProvider}.disconnect");
    }

    // Leave an admin impersonation session (available to the impersonated account).
    Route::post('/impersonate/leave', [AdminUserController::class, 'leaveImpersonation'])->name('impersonate.leave');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/affiliate', [App\Http\Controllers\Admin\AffiliateController::class, 'index'])->name('affiliate');
    Route::put('/affiliate/commissions/{commission}', [App\Http\Controllers\Admin\AffiliateController::class, 'updateCommission'])->name('affiliate.commission');
    Route::put('/affiliate/payouts/{payout}', [App\Http\Controllers\Admin\AffiliateController::class, 'updatePayout'])->name('affiliate.payout');

    Route::get('/blog', [PostController::class, 'index'])->name('blog.index');
    Route::get('/blog/create', [PostController::class, 'create'])->name('blog.create');
    Route::post('/blog', [PostController::class, 'store'])->name('blog.store');
    Route::get('/blog/{post}/edit', [PostController::class, 'edit'])->name('blog.edit');
    Route::put('/blog/{post}', [PostController::class, 'update'])->name('blog.update');
    Route::delete('/blog/{post}', [PostController::class, 'destroy'])->name('blog.destroy');

    Route::get('/help', [HelpArticleController::class, 'index'])->name('help.index');
    Route::get('/help/create', [HelpArticleController::class, 'create'])->name('help.create');
    Route::post('/help', [HelpArticleController::class, 'store'])->name('help.store');
    Route::get('/help/{article}/edit', [HelpArticleController::class, 'edit'])->name('help.edit');
    Route::put('/help/{article}', [HelpArticleController::class, 'update'])->name('help.update');
    Route::delete('/help/{article}', [HelpArticleController::class, 'destroy'])->name('help.destroy');

    Route::get('/pages', [App\Http\Controllers\Admin\PageController::class, 'index'])->name('pages.index');
    Route::get('/pages/create', [App\Http\Controllers\Admin\PageController::class, 'create'])->name('pages.create');
    Route::post('/pages', [App\Http\Controllers\Admin\PageController::class, 'store'])->name('pages.store');
    Route::get('/pages/{page}/edit', [App\Http\Controllers\Admin\PageController::class, 'edit'])->name('pages.edit');
    Route::put('/pages/{page}', [App\Http\Controllers\Admin\PageController::class, 'update'])->name('pages.update');
    Route::delete('/pages/{page}', [App\Http\Controllers\Admin\PageController::class, 'destroy'])->name('pages.destroy');
    Route::get('/users', [AdminUserController::class, 'index'])->name('users');
    Route::get('/users/export', [AdminUserController::class, 'export'])->name('users.export');
    Route::post('/users/bulk', [AdminUserController::class, 'bulk'])->name('users.bulk');
    Route::get('/users/{user}', [AdminUserController::class, 'show'])->name('users.show');
    Route::put('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');
    Route::post('/users/{user}/impersonate', [AdminUserController::class, 'impersonate'])->name('users.impersonate');
    Route::post('/users/{user}/reset-link', [AdminUserController::class, 'sendResetLink'])->name('users.reset-link');
    Route::post('/users/{user}/email', [AdminUserController::class, 'email'])->name('users.email');
    Route::get('/links', [AdminController::class, 'links'])->name('links');
    Route::put('/links/{link}', [AdminController::class, 'updateLink'])->name('links.update');
    Route::get('/plans', [PlanController::class, 'index'])->name('plans');
    Route::get('/plans/create', [PlanController::class, 'create'])->name('plans.create');
    Route::post('/plans', [PlanController::class, 'store'])->name('plans.store');
    Route::get('/plans/{plan}/edit', [PlanController::class, 'edit'])->name('plans.edit');
    Route::put('/plans/{plan}', [PlanController::class, 'update'])->name('plans.update');
    Route::delete('/plans/{plan}', [PlanController::class, 'destroy'])->name('plans.destroy');
    Route::get('/billing', [AdminBillingController::class, 'index'])->name('billing');
    Route::put('/billing/subscriptions/{subscription}', [AdminBillingController::class, 'updateSubscription'])->name('billing.subscriptions.update');
    Route::put('/billing/payments/{payment}', [AdminBillingController::class, 'updatePayment'])->name('billing.payments.update');
    Route::get('/ads', [AdvertisementController::class, 'index'])->name('ads');
    Route::post('/ads/settings', [AdvertisementController::class, 'saveSettings'])->name('ads.settings');
    Route::get('/ads/create', [AdvertisementController::class, 'create'])->name('ads.create');
    Route::post('/ads', [AdvertisementController::class, 'store'])->name('ads.store');
    Route::get('/ads/{ad}/edit', [AdvertisementController::class, 'edit'])->name('ads.edit');
    Route::put('/ads/{ad}', [AdvertisementController::class, 'update'])->name('ads.update');
    Route::post('/ads/{ad}/toggle', [AdvertisementController::class, 'toggle'])->name('ads.toggle');
    Route::delete('/ads/{ad}', [AdvertisementController::class, 'destroy'])->name('ads.destroy');
    Route::get('/content', [ModerationController::class, 'index'])->name('moderation');
    Route::put('/content/bio/{bioPage}', [ModerationController::class, 'updateBioPage'])->name('moderation.bio.update');
    Route::delete('/content/qr/{qr}', [ModerationController::class, 'destroyQrCode'])->name('moderation.qr.destroy');
    Route::put('/content/domains/{domain}', [ModerationController::class, 'updateDomain'])->name('moderation.domains.update');
    Route::get('/tickets', [AdminTicketController::class, 'index'])->name('tickets');
    Route::get('/tickets/{ticket}', [AdminTicketController::class, 'show'])->name('tickets.show');
    Route::post('/tickets/{ticket}/reply', [AdminTicketController::class, 'reply'])->name('tickets.reply');
    Route::put('/tickets/{ticket}', [AdminTicketController::class, 'update'])->name('tickets.update');
    Route::get('/reports', [AdminController::class, 'reports'])->name('reports');
    Route::put('/reports/{report}', [AdminController::class, 'updateReport'])->name('reports.update');
    Route::get('/audit', [AdminController::class, 'audit'])->name('audit');
    Route::get('/broadcast', [BroadcastController::class, 'index'])->name('broadcast');
    Route::post('/broadcast', [BroadcastController::class, 'send'])->name('broadcast.send');
    Route::get('/updates', [UpdateController::class, 'index'])->name('updates');
    Route::post('/updates', [UpdateController::class, 'upload'])->name('updates.upload');
    Route::post('/updates/apply', [UpdateController::class, 'apply'])->name('updates.apply');
    Route::post('/updates/discard', [UpdateController::class, 'discard'])->name('updates.discard');
    Route::get('/languages', [LanguageController::class, 'index'])->name('languages');
    Route::post('/languages', [LanguageController::class, 'store'])->name('languages.store');
    Route::post('/languages/default', [LanguageController::class, 'setDefault'])->name('languages.default');
    Route::post('/languages/scan', [LanguageController::class, 'scan'])->name('languages.scan');
    Route::get('/languages/{code}/edit', [LanguageController::class, 'edit'])->name('languages.edit')->where('code', '[A-Za-z-]+');
    Route::put('/languages/{code}', [LanguageController::class, 'update'])->name('languages.update')->where('code', '[A-Za-z-]+');
    Route::post('/languages/{code}/import', [LanguageController::class, 'import'])->name('languages.import')->where('code', '[A-Za-z-]+');
    Route::get('/languages/{code}/export', [LanguageController::class, 'export'])->name('languages.export')->where('code', '[A-Za-z-]+');
    Route::delete('/languages/{code}', [LanguageController::class, 'destroy'])->name('languages.destroy')->where('code', '[A-Za-z-]+');
    Route::get('/settings', [SettingController::class, 'index'])->name('settings');
    Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');
    Route::post('/settings/geo/update', [SettingController::class, 'updateGeoDatabase'])->name('settings.geo.update');
    Route::post('/settings/geo/download/start', [SettingController::class, 'geoDownloadStart'])->name('settings.geo.download.start');
    Route::post('/settings/geo/download/chunk', [SettingController::class, 'geoDownloadChunk'])->name('settings.geo.download.chunk');
    Route::post('/settings/geo/download/finish', [SettingController::class, 'geoDownloadFinish'])->name('settings.geo.download.finish');
    Route::post('/settings/ai/test', [SettingController::class, 'aiTest'])->name('settings.ai.test');
    Route::post('/settings/email/test', [SettingController::class, 'emailTest'])->name('settings.email.test');
    Route::post('/maintenance', [AdminController::class, 'maintenance'])->name('maintenance');
});

// Public: anonymous link shortening from the landing page (rate-limited).
Route::post('/shorten', [GuestShortenController::class, 'store'])->middleware('throttle:10,1')->name('guest.shorten');

// Public: affiliate referral link — records the click, sets the cookie, sends to register.
Route::get('/ref/{code}', [ReferralController::class, 'track'])->middleware('throttle:30,1')->name('referral.track')->where('code', '[A-Za-z0-9]+');

// Public: one-click demo sign-in (only active when demo mode is on; 404s otherwise).
Route::get('/demo/login/{role}', [DemoController::class, 'login'])
    ->middleware('throttle:30,1')->name('demo.login')->where('role', 'admin|user');

// Public: blog + help center (content marketing + self-serve support).
Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/{slug}', [BlogController::class, 'show'])->name('blog.show')->where('slug', '[A-Za-z0-9\-]+');
Route::get('/help', [HelpController::class, 'index'])->name('help.index');
Route::get('/help/{slug}', [HelpController::class, 'show'])->name('help.show')->where('slug', '[A-Za-z0-9\-]+');
Route::get('/page/{slug}', [PageController::class, 'show'])->name('page.show')->where('slug', '[A-Za-z0-9\-]+');
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');

// Public: switch the UI language (guest + authenticated).
Route::get('/locale/{locale}', [LocaleController::class, 'switch'])->name('locale.switch')->where('locale', '[A-Za-z_-]+');

// Public: social-login OAuth (Google, GitHub, Facebook — guest accessible; 404s when disabled).
foreach (SocialProviders::keys() as $socialProvider) {
    Route::get("/auth/{$socialProvider}/redirect", [SocialAuthController::class, 'redirect'])->defaults('provider', $socialProvider)->middleware('throttle:30,1')->name("auth.{$socialProvider}.redirect");
    Route::get("/auth/{$socialProvider}/callback", [SocialAuthController::class, 'callback'])->defaults('provider', $socialProvider)->middleware('throttle:30,1')->name("auth.{$socialProvider}.callback");
}

// Public: unlock a password-protected link.
Route::post('/unlock/{alias}', [RedirectController::class, 'unlock'])
    ->where('alias', '[A-Za-z0-9\-_]+')
    ->middleware('throttle:10,1')
    ->name('link.unlock');

// Public: report an abusive link.
Route::get('/report', [AbuseReportController::class, 'create'])->name('report.create');
Route::post('/report', [AbuseReportController::class, 'store'])->middleware('throttle:10,1')->name('report.store');

// Public: bio page gates (password unlock + sensitive-content acknowledge).
Route::post('/b/{slug}/unlock', [BioController::class, 'unlock'])->where('slug', '[A-Za-z0-9\-_]+')->middleware('throttle:20,1')->name('bio.unlock');
Route::post('/b/{slug}/reveal', [BioController::class, 'reveal'])->where('slug', '[A-Za-z0-9\-_]+')->name('bio.reveal');
Route::get('/b/{slug}/c/{block}', [BioController::class, 'trackClick'])->where('slug', '[A-Za-z0-9\-_]+')->name('bio.track');
Route::get('/b/{slug}/vcard/{block}', [BioController::class, 'vcard'])->where('slug', '[A-Za-z0-9\-_]+')->name('bio.vcard');
Route::post('/b/{slug}/subscribe', [BioController::class, 'subscribe'])->where('slug', '[A-Za-z0-9\-_]+')->middleware('throttle:10,1')->name('bio.subscribe');
Route::post('/b/{slug}/contact', [BioController::class, 'contact'])->where('slug', '[A-Za-z0-9\-_]+')->middleware('throttle:10,1')->name('bio.contact');

// Payment gateway webhooks / IPN (unauthenticated; CSRF-exempt — see bootstrap/app.php).
Route::post('/billing/webhook/{gateway}', [BillingController::class, 'webhook'])->where('gateway', '[a-z]+')->name('billing.webhook');

// Public: the bundled documentation at /docs. Served through Laravel so it also
// works on a root-.htaccess install (where the /docs *directory* request is handed
// to the front controller). MUST be registered before the short-link fallback so a
// link can never shadow it - this is what stops /docs being resolved as an alias.
Route::get('/docs/{path?}', [DocsController::class, 'serve'])->where('path', '.*')->name('docs');

/*
 | Short-link resolver. Registered as the fallback so every named/static route
 | (home, auth, dashboard, links, assets) is matched first; only unclaimed
 | single-segment paths reach the redirect engine.
 */
Route::fallback([RedirectController::class, 'handle']);
