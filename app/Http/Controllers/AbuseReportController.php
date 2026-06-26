<?php

namespace App\Http\Controllers;

use App\Models\AbuseReport;
use App\Models\Link;
use App\Models\User;
use App\Services\Linking\DomainResolver;
use App\Services\Mail\Postman;
use Illuminate\Http\Request;

class AbuseReportController extends Controller
{
    public function create(Request $request)
    {
        return view('abuse.report', ['alias' => (string) $request->query('alias', '')]);
    }

    public function store(Request $request, DomainResolver $domains)
    {
        $data = $request->validate([
            'alias' => ['nullable', 'string', 'max:190'],
            'reporter_email' => ['nullable', 'email', 'max:190'],
            'reason' => ['required', 'string', 'max:1000'],
            'company' => ['nullable', 'size:0'], // honeypot
        ]);

        $linkId = null;
        if (! empty($data['alias'])) {
            $domain = $domains->resolve($request->getHost());
            $linkId = $domain
                ? Link::where('domain_id', $domain->id)->where('alias', $data['alias'])->value('id')
                : null;
        }

        AbuseReport::create([
            'link_id' => $linkId,
            'reporter_email' => $data['reporter_email'] ?? null,
            'reason' => $data['reason'],
            'status' => 'open',
        ]);

        // Alert staff (honours the per-event toggle + on/off in Settings -> Email).
        $link = $linkId ? Link::find($linkId) : null;
        app(Postman::class)->send(
            'admin_new_report',
            User::where('role', 'admin')->pluck('email')->all(),
            [
                'alias' => ($data['alias'] ?? '') ?: '(unknown)',
                'short_url' => $link ? $request->getSchemeAndHttpHost().'/'.$link->alias : '(not found)',
                'target_url' => $link?->long_url ?? '(not found)',
                'reason' => $data['reason'],
                'action_url' => route('admin.reports'),
            ]
        );

        return redirect()->route('report.create')->with('status', 'Thank you. Our team will review this link.');
    }
}
