<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

/**
 * Ships ready-to-edit Terms, Privacy and Contact pages so a fresh install has the
 * standard legal pages linked in the footer out of the box. Idempotent and
 * non-destructive: firstOrCreate never overwrites an operator's edits on re-run.
 */
class PageSeeder extends Seeder
{
    public function run(): void
    {
        $app = config('linkforge.name', 'our service');

        foreach ($this->pages($app) as $page) {
            Page::firstOrCreate(['slug' => $page['slug']], $page);
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function pages(string $app): array
    {
        $note = "> These pages ship as a starting point. Review and adapt them with your own legal counsel before launch.\n\n";

        return [
            [
                'slug' => 'terms',
                'title' => 'Terms of Service',
                'status' => 'published',
                'show_in_footer' => true,
                'sort' => 1,
                'meta_description' => "The terms that govern your use of {$app}.",
                'body' => $note
                    ."## 1. Acceptance of terms\n\nBy creating an account or using {$app}, you agree to these Terms of Service. If you do not agree, do not use the service.\n\n"
                    ."## 2. Your account\n\nYou are responsible for the activity under your account and for keeping your password secure. You must provide accurate information and be old enough to form a binding contract in your jurisdiction.\n\n"
                    ."## 3. Acceptable use\n\nYou may not use {$app} to shorten or host links to illegal content, malware, phishing, spam, or anything that infringes the rights of others. We may disable links or accounts that violate this policy.\n\n"
                    ."## 4. Service availability\n\nWe work to keep {$app} available and reliable, but the service is provided \"as is\" without warranties of any kind. We are not liable for indirect or consequential damages.\n\n"
                    ."## 5. Termination\n\nYou may close your account at any time. We may suspend or terminate accounts that breach these terms.\n\n"
                    ."## 6. Changes\n\nWe may update these terms from time to time. Continued use after a change means you accept the updated terms.\n\n"
                    ."## 7. Contact\n\nQuestions about these terms? Reach us through the [Contact](/page/contact) page.",
            ],
            [
                'slug' => 'privacy',
                'title' => 'Privacy Policy',
                'status' => 'published',
                'show_in_footer' => true,
                'sort' => 2,
                'meta_description' => "How {$app} collects, uses and protects your data.",
                'body' => $note
                    ."## 1. What we collect\n\nWhen you create an account we store your name and email address. When visitors use your links we record privacy-respecting analytics (such as country, device and referrer). We hash IP addresses and do not store them in raw form.\n\n"
                    ."## 2. How we use it\n\nWe use your data to provide the service, show you analytics, secure the platform and communicate with you about your account.\n\n"
                    ."## 3. Cookies\n\nWe use essential cookies to keep you signed in and remember your preferences. Any optional analytics or marketing cookies are described in the cookie notice.\n\n"
                    ."## 4. Sharing\n\nWe do not sell your personal data. We share it only with the service providers needed to operate {$app} (for example, email delivery), and where required by law.\n\n"
                    ."## 5. Your rights\n\nYou can access, correct, export or delete your account data at any time from your account settings, or by contacting us.\n\n"
                    ."## 6. Data retention\n\nWe keep your data for as long as your account is active. Raw click events are pruned automatically after the retention period.\n\n"
                    ."## 7. Contact\n\nFor any privacy request, reach us through the [Contact](/page/contact) page.",
            ],
            [
                'slug' => 'contact',
                'title' => 'Contact',
                'status' => 'published',
                'show_in_footer' => true,
                'sort' => 3,
                'meta_description' => "Get in touch with the {$app} team.",
                'body' => "## Get in touch\n\nWe would love to hear from you.\n\n"
                    ."- **Support:** customers can open a ticket from the dashboard for the fastest reply.\n"
                    ."- **Email:** replace this with your support email address.\n"
                    ."- **Business hours:** add the hours your team is available.\n\n"
                    .'Edit this page in the admin panel under **Content -> Pages** to add your real contact details.',
            ],
        ];
    }
}
