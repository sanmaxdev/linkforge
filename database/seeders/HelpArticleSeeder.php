<?php

namespace Database\Seeders;

use App\Models\HelpArticle;
use Illuminate\Database\Seeder;

/**
 * Starter Help Center content: a library of professional, brand-neutral articles
 * that ship with a fresh install (operators can edit or delete them) and are also
 * used by the public demo. Idempotent — re-running updates by slug, never dupes.
 */
class HelpArticleSeeder extends Seeder
{
    public function run(): void
    {
        $sort = [];
        foreach ($this->articles() as $a) {
            $cat = $a['category'];
            $sort[$cat] = ($sort[$cat] ?? 0) + 1;

            HelpArticle::updateOrCreate(
                ['slug' => $a['slug']],
                [
                    'category' => $cat,
                    'title' => $a['title'],
                    'excerpt' => $a['excerpt'],
                    'body' => $a['body'],
                    'status' => 'published',
                    'sort' => $sort[$cat],
                ],
            );
        }
    }

    /** @return array<int, array{category:string,title:string,slug:string,excerpt:string,body:string}> */
    private function articles(): array
    {
        return [
            // ---------------- Getting started ----------------
            ['category' => 'Getting started', 'title' => 'Creating your first short link', 'slug' => 'first-short-link',
                'excerpt' => 'Shorten any URL in under a minute and start tracking clicks.',
                'body' => "Short links are the core of your account. Here is the fastest way to make one.\n\n## Steps\n\n1. Open **Links** in the sidebar and click **New link**.\n2. Paste your long destination URL into the **Destination** field.\n3. (Optional) Add a **title** so the link is easy to recognise later, and a **custom alias** to brand the ending.\n4. Click **Create**.\n\nYour short link is ready to copy and share anywhere. Every visit is recorded automatically — open the link's **Analytics** to watch clicks, countries and devices in real time.\n\n## Tips\n\n- Leave the alias blank to get a short random code.\n- Use a **campaign** to group links from the same promotion so you can measure them together.\n- The destination can be changed at any time without changing the short link."],

            ['category' => 'Getting started', 'title' => 'A tour of your dashboard', 'slug' => 'dashboard-tour',
                'excerpt' => 'What each area of the dashboard does, so you know where to go.',
                'body' => "When you sign in you land on the **Dashboard** — a snapshot of how your links are performing. Here is what the main areas do.\n\n- **Dashboard** — your key numbers at a glance: total clicks, top links, and recent activity.\n- **Links** — create, search, filter and manage every short link.\n- **Campaigns** — group related links and see their combined performance.\n- **QR Codes** — design styled, scannable codes for print and packaging.\n- **Bio Pages** — build mobile link-in-bio profiles.\n- **Analytics** — deep, filterable click reports with CSV export.\n- **Account** — your profile, security settings and plan.\n\nEverything updates live as visitors click your links, so you can leave the dashboard open during a launch."],

            ['category' => 'Getting started', 'title' => 'Setting up your profile and password', 'slug' => 'account-profile',
                'excerpt' => 'Add your name and avatar, and keep your sign-in secure.',
                'body' => "Open **Account** from the menu to manage your profile and security.\n\n## Profile\n\nSet your **name**, **email** and an **avatar** (uploaded images are resized automatically). Your name appears on your bio pages and in support replies.\n\n## Security\n\n- Change your **password** at any time.\n- Turn on **two-factor authentication** for an extra layer of protection.\n- Register a **passkey** to sign in without a password using Touch ID, Windows Hello or a security key.\n\nWe recommend enabling two-factor authentication as soon as your account is set up."],

            // ---------------- Short links ----------------
            ['category' => 'Short links', 'title' => 'Custom aliases and branded endings', 'slug' => 'custom-aliases',
                'excerpt' => 'Replace the random code with words your audience will trust.',
                'body' => "A custom alias is the part after the slash — for example `/summer` instead of `/a1b2c3`. Branded endings are easier to remember and get more clicks because they look trustworthy.\n\n## How to set one\n\nWhen creating or editing a link, type your alias in the **Custom alias** field. Use letters, numbers, hyphens and underscores.\n\n## Good to know\n\n- Each alias must be unique on its domain.\n- A few short words are reserved because they match built-in pages.\n- Pair a custom alias with a [custom domain](custom-domain) for fully branded links like `go.yourbrand.com/summer`."],

            ['category' => 'Short links', 'title' => 'Password-protecting a link', 'slug' => 'link-passwords',
                'excerpt' => 'Require a password before a visitor is redirected.',
                'body' => "You can lock any link so only people with the password reach the destination — useful for private downloads, draft pages or paid resources.\n\n## Steps\n\n1. Edit the link and open the **Protection** options.\n2. Enter a **password**.\n3. Save.\n\nVisitors now see a simple password screen first. The password is stored securely (hashed) and never shown again, so keep your own copy. Remove the password any time to make the link public again.\n\nClicks on a protected link are still counted, so you keep full analytics."],

            ['category' => 'Short links', 'title' => 'Link expiry and click limits', 'slug' => 'link-expiry',
                'excerpt' => 'Make a link stop working after a date or a number of clicks.',
                'body' => "Sometimes a link should only work for a while. Two settings control this.\n\n- **Expiry date** — the link stops redirecting after the date and time you choose.\n- **Click limit** — the link stops after it has been clicked a set number of times (great for limited offers or invites).\n\nWhen a link is expired or has hit its limit, visitors see a friendly \"link unavailable\" page instead of the destination. You can clear or extend either setting at any time to re-activate the link."],

            ['category' => 'Short links', 'title' => 'Adding UTM tracking parameters', 'slug' => 'utm-builder',
                'excerpt' => 'Tag links so Google Analytics and other tools know where traffic came from.',
                'body' => "UTM parameters are tags added to your destination URL that analytics tools read to attribute traffic to a source, medium and campaign.\n\n## Using the builder\n\nWhen creating a link, open the **UTM** section and fill in the fields you need:\n\n- **Source** — where the link is shared (e.g. `newsletter`, `instagram`).\n- **Medium** — the channel type (e.g. `email`, `social`, `cpc`).\n- **Campaign** — the promotion name (e.g. `spring-sale`).\n- **Term** and **Content** — optional, for paid keywords or A/B variants.\n\nThe tags are appended to your destination automatically, so the visitor lands on a fully tagged URL while you keep a clean short link to share."],

            ['category' => 'Short links', 'title' => 'A/B testing with link rotation', 'slug' => 'ab-rotation',
                'excerpt' => 'Split traffic between several destinations from one short link.',
                'body' => "Rotation lets a single short link send visitors to two or more destinations, so you can test which page performs best or spread load across mirrors.\n\n## How it works\n\n1. Edit the link and add multiple **destination URLs**.\n2. Give each a **weight** to control how much traffic it receives (for an even split, use equal weights).\n3. Save.\n\nIncoming clicks are distributed according to the weights. Compare the destinations' own conversion rates to find the winner, then point the link fully at the best performer."],

            ['category' => 'Short links', 'title' => 'Bulk creating and importing links', 'slug' => 'bulk-import',
                'excerpt' => 'Create many links at once, or migrate from another platform.',
                'body' => "When you have lots of links to make, **Links → Bulk** saves time.\n\n## Paste a list\n\nPaste one destination URL per line. Each becomes a short link, and you can apply a shared **campaign** and **tags** to the whole batch.\n\n## Import a CSV\n\nUpload a spreadsheet or an export from another shortener. Columns are detected automatically — an export with *Long URL*, *Title* and *Tags* headers maps straight across, and a simple one-column file works too. Custom aliases are kept where they are still available.\n\nEvery row is screened for safety, duplicates are skipped, and the batch respects your plan's link limit. A summary shows exactly what was created and what was skipped (and why)."],

            ['category' => 'Short links', 'title' => 'Mobile deep links', 'slug' => 'deep-links',
                'excerpt' => 'Open a native app instead of the browser on phones, with a web fallback.',
                'body' => "Deep links let a short link open a destination *inside a mobile app* (for example a profile in the app rather than the website), while still working everywhere else.\n\n## Setting it up\n\nEdit a link and open the **Mobile deep links** section, then enter the app URI for **iOS** and **Android** (for example `myapp://product/123`).\n\n## What visitors experience\n\n- On a phone with the app installed, the app opens directly.\n- If the app is not installed, or the visitor is on desktop, they go to your normal web destination after a moment.\n\nDeep links are a plan feature — if you do not see the section, it is available on a higher plan."],

            // ---------------- Custom domains ----------------
            ['category' => 'Custom domains', 'title' => 'Connecting a custom domain', 'slug' => 'custom-domain',
                'excerpt' => 'Use your own branded domain for short links.',
                'body' => "A custom domain replaces the default one so your links read like `go.yourbrand.com/summer`. Branded links look more trustworthy and reinforce your brand on every share.\n\n## Steps\n\n1. Open **Custom domains** and click **Add domain**.\n2. Enter the domain or subdomain you want to use (a subdomain like `go.yourbrand.com` is recommended).\n3. Add the DNS record shown to you (see [Pointing your DNS](domain-dns)).\n4. Click **Verify**. Once DNS has propagated, the domain goes live.\n\nYou can set one domain as your **default** so new links use it automatically."],

            ['category' => 'Custom domains', 'title' => 'Pointing your DNS (CNAME and A records)', 'slug' => 'domain-dns',
                'excerpt' => 'The exact DNS record to add at your domain registrar.',
                'body' => "After adding a domain in the app, you point it at the platform with one DNS record at your registrar (GoDaddy, Namecheap, Cloudflare and so on).\n\n## Subdomain (recommended)\n\nAdd a **CNAME** record:\n\n- **Host / Name:** your subdomain, e.g. `go`\n- **Value / Target:** the CNAME target shown on the add-domain screen\n\n## Root domain\n\nIf you must use a bare root domain, add an **A** record pointing to the **server IP** shown on the same screen, because many registrars do not allow a CNAME on the root.\n\nDNS changes can take from a few minutes up to a few hours to propagate. Click **Verify** again once it resolves. After that, enable HTTPS (most hosts issue a free certificate automatically)."],

            // ---------------- QR codes ----------------
            ['category' => 'QR codes', 'title' => 'Designing a branded QR code', 'slug' => 'qr-design',
                'excerpt' => 'Colours, shapes and a centre logo for codes that match your brand.',
                'body' => "The **QR Codes** studio turns any link or content into a styled, scannable code.\n\n## What you can customise\n\n- **Body and eye shapes** — square, dots, rounded, classy and more.\n- **Colours** — solid foreground/background, or a two-colour gradient.\n- **Logo** — drop your logo into the centre.\n- **Content type** — a URL, plain text, Wi-Fi credentials, a vCard contact, email, phone or SMS.\n\n## Keep it scannable\n\nKeep good contrast between the code and its background, leave the quiet margin around it, and test the final code with a couple of phones before printing at size."],

            ['category' => 'QR codes', 'title' => 'Dynamic vs static QR codes', 'slug' => 'qr-dynamic',
                'excerpt' => 'When to use each, and why dynamic codes are editable after printing.',
                'body' => "There are two kinds of QR code, and the difference matters once a code is printed.\n\n## Static\n\nThe destination is encoded directly in the code. It works forever with no dependency, but you **cannot change** where it points after it is printed.\n\n## Dynamic\n\nThe code points at a short link, and the short link points at your destination. Because you can edit the short link, you can **change the destination at any time** — even after thousands of codes are in the wild — and you get full **scan analytics**.\n\nUse dynamic codes for anything printed (packaging, posters, business cards) where the target might change or you want to measure scans."],

            // ---------------- Bio pages ----------------
            ['category' => 'Bio pages', 'title' => 'Building a link-in-bio page', 'slug' => 'bio-build',
                'excerpt' => 'A mobile profile that holds all your links in one place.',
                'body' => "A bio page is a mobile-first profile (like a personal landing page) hosted on your domain at `yourdomain.com/your-handle`. It is perfect for the single link a social profile allows.\n\n## Build it\n\n1. Open **Bio Pages** and click **New page**, then choose a **handle** (the part after the slash).\n2. Add a **profile** — avatar, name and a short bio.\n3. Add **blocks**: link buttons, headings, text, images, video embeds, a newsletter sign-up and more. Drag to reorder.\n4. Pick a **theme** — colours, background and fonts. A live preview shows your changes instantly.\n5. **Publish**.\n\nEvery block click is tracked, so you can see which links your audience taps most."],

            ['category' => 'Bio pages', 'title' => 'Collecting leads on a bio page', 'slug' => 'bio-leads',
                'excerpt' => 'Capture email subscribers and contact messages from visitors.',
                'body' => "Bio pages can do more than send clicks away — they can grow your audience.\n\n## Newsletter block\n\nAdd a **newsletter** block to invite visitors to subscribe with their email. Addresses are collected on your page and exportable as CSV.\n\n## Contact block\n\nAdd a **contact** block so visitors can send you a message (name, email and text) without leaving the page. Messages appear in your dashboard.\n\nBoth blocks include spam protection. Review collected leads and messages under the page's **Leads** view, and export them whenever you need them in another tool."],

            // ---------------- Analytics ----------------
            ['category' => 'Analytics', 'title' => 'Understanding your click data', 'slug' => 'click-data',
                'excerpt' => 'Reading total clicks, unique visitors and bot traffic.',
                'body' => "Open any link's **Analytics** (or the account-wide **Analytics** page) to see how your traffic breaks down.\n\n## The headline numbers\n\n- **Total clicks** — every redirect served.\n- **Unique visitors** — distinct people, so repeat clicks from one person are not double-counted.\n- **Bot clicks** — automated traffic (link previews, scanners) shown separately so it does not inflate your real numbers.\n\n## The breakdowns\n\nBelow the totals you will find **clicks over time**, **top countries** and a world map, **cities**, **devices**, **browsers** and **referrers**. Use the date range selector to focus on a launch window, and **Export CSV** to take the data into a spreadsheet."],

            ['category' => 'Analytics', 'title' => 'Exporting analytics to CSV', 'slug' => 'export-analytics',
                'excerpt' => 'Download your click data for spreadsheets and reports.',
                'body' => "Every analytics view has an **Export CSV** button. The export respects the **date range** and any **filters** you have applied, so you get exactly the slice you are looking at.\n\n## What you can export\n\n- A single link's analytics.\n- A whole campaign's combined analytics.\n- Bio page analytics.\n\nThe file opens in Excel, Google Sheets or Numbers, making it easy to build client reports or combine link data with the rest of your marketing numbers."],

            ['category' => 'Analytics', 'title' => 'How country and device data works', 'slug' => 'geo-data',
                'excerpt' => 'Where location, device and referrer stats come from.',
                'body' => "Your analytics are built from the details of each click, resolved privately on your own server.\n\n- **Country and city** come from the visitor's IP address using a local geo database, or from your CDN's location headers if your site is behind one. No third-party tracking is involved.\n- **Device, OS and browser** are read from the request's user-agent.\n- **Referrer** shows the site a visitor came from, when the browser provides it.\n\nIf country data is empty, your install may only have the country-level database; adding a city-level database (a one-click download for the operator) unlocks city detail. IP addresses themselves are never stored in a way that identifies an individual."],

            // ---------------- Marketing ----------------
            ['category' => 'Marketing', 'title' => 'Adding a retargeting pixel', 'slug' => 'retargeting-pixels',
                'excerpt' => 'Fire Meta, Google, TikTok and other pixels on your links.',
                'body' => "Retargeting pixels let you build advertising audiences from the people who click your links, so you can show them ads later.\n\n## Steps\n\n1. Open **Pixels** and click **Add pixel**.\n2. Choose a provider — Meta (Facebook), Google, TikTok, LinkedIn, X, Pinterest, Snapchat, Reddit and more are supported.\n3. Paste your **pixel / tag ID** from that provider.\n4. Attach the pixel to the links or bio pages where you want it to fire.\n\nWhen someone clicks, the pixel loads on the brief interstitial before they continue, adding them to your retargeting audience. You can attach more than one pixel at a time."],

            // ---------------- Account & security ----------------
            ['category' => 'Account & security', 'title' => 'Enabling two-factor authentication', 'slug' => 'two-factor',
                'excerpt' => 'Protect your account with a second step at sign-in.',
                'body' => "Two-factor authentication (2FA) means signing in needs both your password and a code from your phone, so a stolen password alone is not enough.\n\n## Set it up\n\n1. Open **Account → Security**.\n2. Click **Enable two-factor authentication**.\n3. Scan the QR code with an authenticator app (Google Authenticator, Authy, 1Password, etc.).\n4. Enter the 6-digit code to confirm.\n5. **Save your recovery codes** somewhere safe — they let you back in if you lose your phone.\n\nFrom then on, you will enter a code from the app each time you sign in on a new device."],

            ['category' => 'Account & security', 'title' => 'Signing in with passkeys', 'slug' => 'passkeys',
                'excerpt' => 'Passwordless login with Touch ID, Face ID or Windows Hello.',
                'body' => "A passkey replaces your password with your device's built-in security — your fingerprint, face, or a hardware security key. It cannot be phished or reused, making it the most secure way to sign in.\n\n## Register one\n\n1. Open **Account → Security**.\n2. Click **Add a passkey** and follow your device's prompt (Touch ID, Face ID, Windows Hello, or a security key).\n\nNext time you sign in, choose **Use a passkey** and authenticate with your device — no password to type. You can register several passkeys (for example your laptop and your phone) and remove any you no longer use.\n\n> Passkeys require your site to be served over HTTPS."],

            // ---------------- Billing ----------------
            ['category' => 'Billing & plans', 'title' => 'Upgrading or changing your plan', 'slug' => 'upgrade-plan',
                'excerpt' => 'Move between plans and see what changes.',
                'body' => "Open **Billing** to see the available plans side by side, your current plan, and how much of each limit you are using.\n\n## Changing plan\n\nPick a plan and continue to checkout. Depending on how the platform is configured, you will pay by card, PayPal, crypto, or be invoiced. As soon as the payment is confirmed, your new limits and features apply.\n\n## Downgrading\n\nYou can move to a smaller plan at any time. Your existing data is kept; the lower limits simply apply going forward. If you are over a limit (for example more links than the smaller plan allows), existing items keep working but you will not be able to add new ones until you are back under the limit."],

            // ---------------- Developers ----------------
            ['category' => 'Developers & API', 'title' => 'Creating an API key', 'slug' => 'api-keys',
                'excerpt' => 'Generate a token to use the REST API.',
                'body' => "The REST API lets you create and manage links from your own code or tools like Zapier and Make.\n\n## Get a key\n\n1. Open the **Developer** area and go to **API tokens**.\n2. Click **New token**, give it a name, and create it.\n3. **Copy the token now** — for security it is shown only once.\n\n## Using it\n\nSend the token as a Bearer header on every request:\n\n```\nAuthorization: Bearer YOUR_TOKEN\n```\n\nThe API base is `/api/v1`. You can create links, fetch analytics and more. Revoke a token at any time from the same screen if it is ever exposed."],

            ['category' => 'Developers & API', 'title' => 'Receiving webhooks', 'slug' => 'webhooks',
                'excerpt' => 'Get notified in real time when events happen.',
                'body' => "Webhooks push events to your own server as they happen, so you can react automatically — log a click, sync a CRM, or trigger an automation.\n\n## Set one up\n\n1. In the **Developer** area, open **Webhooks** and click **Add endpoint**.\n2. Enter the **URL** on your server that should receive events.\n3. Choose which **events** to subscribe to (for example a new link, or a click).\n\n## Verifying delivery\n\nEach request is signed so you can confirm it genuinely came from your account — check the signature header against your endpoint's secret before trusting the payload. For security, endpoints pointing at internal or loopback addresses are rejected, and delivery relies on the scheduled task being installed."],
        ];
    }
}
