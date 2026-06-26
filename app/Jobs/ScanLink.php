<?php

namespace App\Jobs;

use App\Models\Link;
use App\Models\SafetyScan;
use App\Models\Webhook;
use App\Services\Safety\ThreatScanner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Deep threat scan for a link. Runs synchronously at create time
 * (ScanLink::dispatchSync) and asynchronously for periodic rescans.
 */
class ScanLink implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $linkId) {}

    public function handle(ThreatScanner $scanner): void
    {
        $link = Link::find($this->linkId);
        if (! $link) {
            return;
        }

        $result = $scanner->scan($link->long_url);

        foreach ($result['scans'] as $scan) {
            SafetyScan::create([
                'link_id' => $link->id,
                'provider' => $scan['provider'],
                'verdict' => $scan['verdict'],
                'raw' => $scan['raw'] ?? null,
                'scanned_at' => now(),
            ]);
        }

        $flagged = ['flagged', 'blocked'];
        $wasFlagged = in_array($link->safety_status, $flagged, true);

        $link->forceFill([
            'safety_status' => $result['status'],
            'safety_score' => $result['score'],
        ])->save();

        Link::forgetCache($link->domain_id, $link->alias);

        // Notify subscribed webhooks the first time a link is flagged or blocked.
        if (! $wasFlagged && in_array($result['status'], $flagged, true)) {
            Webhook::fire($link->user_id, 'link.flagged', [
                'id' => $link->id,
                'alias' => $link->alias,
                'long_url' => $link->long_url,
                'status' => $result['status'],
                'score' => $result['score'],
            ]);
        }
    }
}
