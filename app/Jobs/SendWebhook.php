<?php

namespace App\Jobs;

use App\Models\Webhook;
use App\Support\SafeUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class SendWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @param array<string, mixed> $payload */
    public function __construct(public int $webhookId, public string $event, public array $payload) {}

    public function handle(): void
    {
        $webhook = Webhook::find($this->webhookId);
        if (! $webhook || ! $webhook->is_active) {
            return;
        }

        // SSRF guard: never deliver to an internal/loopback/reserved address
        // (re-checked at send time in case DNS changed since the webhook was saved).
        if (! SafeUrl::isSafe((string) $webhook->url)) {
            return;
        }

        $body = json_encode(['event' => $this->event, 'data' => $this->payload, 'sent_at' => now()->toIso8601String()]);
        $signature = hash_hmac('sha256', $body, (string) $webhook->secret);

        try {
            Http::timeout(8)
                ->withHeaders([
                    'X-LinkForge-Event' => $this->event,
                    'X-LinkForge-Signature' => $signature,
                ])
                ->withBody($body, 'application/json')
                ->post($webhook->url);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
