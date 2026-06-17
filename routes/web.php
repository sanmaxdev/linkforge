<?php

use App\Http\Controllers\AbuseReportController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Admin\BillingController as AdminBillingController;
use App\Http\Controllers\Admin\LanguageController;
use App\Http\Controllers\Admin\ModerationController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\TicketController as AdminTicketController;
use App\Http\Controllers\Admin\UpdateController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AiController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\ApiTokenController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\BioController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DomainController;
use App\Http\Controllers\Install\InstallController;
use App\Http\Controllers\LinkController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PixelController;
use App\Http\Controllers\QrController;
use App\Http\Controllers\RedirectController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

// First-run web installer (sealed off once the site is installed).
Route::middleware('install.guard')->prefix('install')->name('install.')->group(function () {
    Route::get('/', [InstallController::class, 'welcome'])->name('welcome');
    Route::get('/database', [InstallController::class, 'database'])->name('database');
    Route::post('/database', [InstallController::class, 'saveDatabase'])->name('database.save');
    Route::get('/account', [InstallController::class, 'account'])->name('account');
    Route::post('/account', [InstallController::class, 'saveAccount'])->name('account.save');
    Route::get('/license', [InstallController::class, 'license'])->name('license');
    Route::post('/license', [InstallController::class, 'saveLicense'])->name('license.save');
    Route::get('/complete', [InstallController::class, 'complete'])->name('complete');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/links', [LinkController::class, 'index'])->name('links.index');
    Route::get('/links/create', [LinkController::class, 'create'])->name('links.create');
    Route::post('/links', [LinkController::class, 'store'])->middleware('throttle:30,1')->name('links.store');
    Route::get('/links/{link}/edit', [LinkController::class, 'edit'])->name('links.edit');
    Route::put('/links/{link}', [LinkController::class, 'update'])->name('links.update');
    Route::delete('/links/{link}', [LinkController::class, 'destroy'])->name('links.destroy');
    // NB: avoid the URL segment "stats" — cPanel/ModSecurity reserve & 403 it. Route names kept for back-compat.
    Route::get('/links/{link}/analytics', [AnalyticsController::class, 'show'])->name('links.stats');
    Route::get('/links/{link}/analytics/export', [AnalyticsController::class, 'exportLink'])->name('links.stats.export');

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

    Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
    Route::post('/billing/{plan}/subscribe', [BillingController::class, 'subscribe'])->name('billing.subscribe');
    Route::get('/billing/return/{gateway}', [BillingController::class, 'return'])->name('billing.return');

    Route::get('/api-tokens', [ApiTokenController::class, 'index'])->name('tokens.index');
    Route::post('/api-tokens', [ApiTokenController::class, 'store'])->name('tokens.store');
    Route::delete('/api-tokens/{token}', [ApiTokenController::class, 'destroy'])->name('tokens.destroy');

    Route::get('/webhooks', [WebhookController::class, 'index'])->name('webhooks.index');
    Route::post('/webhooks', [WebhookController::class, 'store'])->name('webhooks.store');
    Route::delete('/webhooks/{webhook}', [WebhookController::class, 'destroy'])->name('webhooks.destroy');

    // Support tickets (customer side).
    Route::get('/support', [SupportController::class, 'index'])->name('support.index');
    Route::get('/support/create', [SupportController::class, 'create'])->name('support.create');
    Route::post('/support', [SupportController::class, 'store'])->middleware('throttle:20,1')->name('support.store');
    Route::get('/support/{ticket}', [SupportController::class, 'show'])->name('support.show');
    Route::post('/support/{ticket}/reply', [SupportController::class, 'reply'])->middleware('throttle:30,1')->name('support.reply');
    Route::post('/support/{ticket}/close', [SupportController::class, 'close'])->name('support.close');

    // AI assist (alias suggestions, "ask your links"). Inert without an API key.
    Route::post('/ai/alias', [AiController::class, 'suggestAlias'])->middleware('throttle:20,1')->name('ai.alias');
    Route::post('/ai/ask', [AiController::class, 'ask'])->middleware('throttle:20,1')->name('ai.ask');

    // Account settings (profile, avatar, password, 2FA, account deletion).
    Route::get('/account', [AccountController::class, 'edit'])->name('account');
    Route::put('/account/profile', [AccountController::class, 'updateProfile'])->name('account.profile');
    Route::put('/account/password', [AccountController::class, 'updatePassword'])->name('account.password');
    Route::delete('/account', [AccountController::class, 'destroy'])->name('account.destroy');

    // Connected accounts (link / unlink Google to the signed-in user).
    Route::get('/account/connections/google', [GoogleAuthController::class, 'connect'])->middleware('throttle:30,1')->name('account.google.connect');
    Route::delete('/account/connections/google', [GoogleAuthController::class, 'disconnect'])->name('account.google.disconnect');

    // Leave an admin impersonation session (available to the impersonated account).
    Route::post('/impersonate/leave', [AdminUserController::class, 'leaveImpersonation'])->name('impersonate.leave');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/users', [AdminUserController::class, 'index'])->name('users');
    Route::get('/users/export', [AdminUserController::class, 'export'])->name('users.export');
    Route::get('/users/{user}', [AdminUserController::class, 'show'])->name('users.show');
    Route::put('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');
    Route::post('/users/{user}/impersonate', [AdminUserController::class, 'impersonate'])->name('users.impersonate');
    Route::post('/users/{user}/reset-link', [AdminUserController::class, 'sendResetLink'])->name('users.reset-link');
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
});

// Public: switch the UI language (guest + authenticated).
Route::get('/locale/{locale}', [LocaleController::class, 'switch'])->name('locale.switch')->where('locale', '[A-Za-z_-]+');

// Public: "Sign in with Google" OAuth (guest accessible; 404s when disabled).
Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])->middleware('throttle:30,1')->name('auth.google.redirect');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->middleware('throttle:30,1')->name('auth.google.callback');

// Public: unlock a password-protected link.
Route::post('/unlock/{alias}', [RedirectController::class, 'unlock'])
    ->where('alias', '[A-Za-z0-9\-_]+')
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

/*
 | Short-link resolver. Registered as the fallback so every named/static route
 | (home, auth, dashboard, links, assets) is matched first; only unclaimed
 | single-segment paths reach the redirect engine.
 */
Route::fallback([RedirectController::class, 'handle']);
