<?php

namespace App\Services\Mail;

use App\Mail\TemplatedMail;
use App\Models\EmailTemplate;
use App\Support\Demo;
use App\Support\EmailEvents;
use Illuminate\Support\Facades\Mail;

/**
 * Sends a transactional email for a named event, honouring the per-event
 * on/off toggle and operator-edited copy. Placeholders ({{ token }}) are
 * substituted from the supplied data plus global app tokens. Sending is
 * best-effort: a misconfigured mailer never breaks the originating request.
 */
class Postman
{
    /**
     * @param  string|array<int,string>  $to
     * @param  array<string,mixed>  $data  Values for {{ placeholders }} (+ optional action_url for the CTA).
     */
    public function send(string $event, string|array $to, array $data = []): void
    {
        // Never send real email from the public demo.
        if (Demo::enabled()) {
            return;
        }

        $tpl = EmailTemplate::resolve($event);
        if (! $tpl || ! $tpl['enabled'] || empty($to)) {
            return;
        }

        $data = array_merge([
            'app_name' => config('linkforge.name'),
            'app_url' => config('app.url'),
        ], $data);

        $subject = $this->render($tpl['subject'], $data);
        $body = $this->render($tpl['body'], $data);
        $actionText = EmailEvents::EVENTS[$event]['action_text'] ?? null;
        $actionUrl = $data['action_url'] ?? null;

        try {
            Mail::to($to)->send(new TemplatedMail($subject, $body, $actionUrl, $actionText));
        } catch (\Throwable $e) {
            report($e); // log and move on — never surface a mail failure to the user action
        }
    }

    private function render(string $template, array $data): string
    {
        return preg_replace_callback('/\{\{\s*(\w+)\s*\}\}/', fn ($m) => (string) ($data[$m[1]] ?? ''), $template);
    }
}
