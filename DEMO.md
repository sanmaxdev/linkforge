# Hosting a live demo

LinkForge has a built-in **demo mode** that turns an install into a safe public
showcase: visitors can try every feature, but destructive and configuration
changes are blocked, no real email is sent, and a "this is a demo" bar + buy CTA
are shown. One-click logins let people explore as both an **admin** and a
**customer** without a password.

> ⚠️ **Run demo mode on a SEPARATE install only** (its own domain/subdomain and
> its own database). Never enable it on your production site — it disables
> account/security/settings changes and exposes one-click admin access.

---

## 1. Set up a separate install

Install LinkForge as usual on a dedicated host, e.g. **`demo.yoursite.com`**, with
its **own database** (do not share your production DB). Complete the web installer.

Recommended layout (mirrors how CodeCanyon competitors host demos):

| URL | Purpose |
|---|---|
| `https://demo.yoursite.com` | Marketing page + live shortener + Blog/Help |
| `https://demo.yoursite.com/login` | One-click **Enter as Admin** / **Enter as Customer** |

## 2. Turn on demo mode

Demo mode is **env-only** (there is intentionally no admin toggle, so customer
installs never expose it). Add to the demo server's `.env`:

```env
LF_DEMO=true
LF_DEMO_BUY_URL=https://codecanyon.net/item/your-item
```

Then clear the config cache:

```bash
php artisan config:clear
```

## 3. Seed the demo data + schedule the reset

Populate the demo accounts and sample data:

```bash
php artisan demo:reset --force
```

This creates two accounts and a rich sample dataset (links, campaigns, tags, a
deep link, pixels, an affiliate dashboard, blog posts, help articles):

| Role | Login | How |
|---|---|---|
| Admin | `admin@demo.test` | "Enter as Admin" button (no password) |
| Customer | `user@demo.test` | "Enter as Customer" button (no password) |

The customer account is on the top plan, so visitors can try **every** paid
feature (deep links, monetization, pixels, custom domains, AI…).

Keep the demo fresh automatically — it resets **hourly** via the scheduler once
your cron is set up (the same single cron entry the app already uses):

```cron
* * * * * php /path/to/demo/artisan schedule:run >> /dev/null 2>&1
```

(`demo:reset` is a no-op on non-demo installs, so it's safe everywhere.)

## 4. What visitors can and can't do

**They can** (it resets hourly): create/edit/delete their own links, campaigns,
tags, QR codes, pixels, bio pages; bulk-import; run the analytics; "upgrade" via
the offline gateway; use the affiliate dashboard; and, as admin, browse the whole
admin panel and manage blog/help content.

**They can't:** change site settings, run the updater, edit languages, change the
demo accounts' email/password, delete accounts, or register new accounts
(one-click logins are the entry point). Real email is never sent.

## 5. Isolation from your real app

Demo mode is **off by default** and every behaviour is gated behind it, so a
normal customer install is never affected:

- `LF_DEMO` defaults to `false`; the admin toggle defaults to off.
- `demo:reset` does nothing unless demo mode is on (or `--force`).
- The demo bar, popup, one-click logins and `/demo/login/*` routes only appear /
  work in demo mode.

Because the demo is a **separate install with its own database**, nothing it does
can touch your production data. Keep the two on different domains and databases.

## 6. Tips for a great demo

- Use a subdomain (`demo.…`) and link to it from your sales page.
- Set `LF_DEMO_BUY_URL` to your CodeCanyon item so every CTA converts.
- Pre-configure a branded custom domain and a couple of social logins (with real
  OAuth apps) if you want those shown — otherwise they stay hidden.
- Let the short links accumulate real clicks: visitors clicking the sample links
  generate live analytics, so the dashboards look active between resets.
