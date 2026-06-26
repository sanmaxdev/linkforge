<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Ticket;
use App\Services\Mail\Postman;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');
        $q = trim((string) $request->query('q', ''));

        $tickets = Ticket::with('user')
            ->when(array_key_exists((string) $status, Ticket::STATUSES), fn ($x) => $x->where('status', $status))
            ->when($q !== '', fn ($x) => $x->where(fn ($w) => $w->where('subject', 'like', "%{$q}%")
                ->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%{$q}%")->orWhere('name', 'like', "%{$q}%"))))
            ->orderByRaw("CASE status WHEN 'open' THEN 0 WHEN 'answered' THEN 1 ELSE 2 END")
            ->latest('last_reply_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.tickets.index', [
            'tickets' => $tickets,
            'status' => $status,
            'q' => $q,
            'statuses' => Ticket::STATUSES,
            'openCount' => Ticket::where('status', 'open')->count(),
        ]);
    }

    public function show(Request $request, Ticket $ticket)
    {
        return view('admin.tickets.show', [
            'ticket' => $ticket->load(['messages.user', 'user.plan']),
            'statuses' => Ticket::STATUSES,
            'priorities' => Ticket::PRIORITIES,
        ]);
    }

    public function reply(Request $request, Ticket $ticket)
    {
        $data = $request->validate(['message' => ['required', 'string', 'max:8000']]);

        $ticket->addMessage($data['message'], 'admin', $request->user()->id);
        AuditLog::record('ticket.reply', "Replied to ticket #{$ticket->id}", $ticket);

        $ticket->loadMissing('user');
        if ($ticket->user) {
            app(Postman::class)->send('ticket_reply', $ticket->user->email, [
                'name' => $ticket->user->name, 'ticket_id' => $ticket->id, 'ticket_subject' => $ticket->subject,
                'action_url' => route('support.show', $ticket),
            ]);
        }

        return back()->with('status', 'Reply sent.');
    }

    public function update(Request $request, Ticket $ticket)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Ticket::STATUSES))],
            'priority' => ['required', Rule::in(array_keys(Ticket::PRIORITIES))],
        ]);

        $ticket->update($data);
        AuditLog::record('ticket.update', "Ticket #{$ticket->id} set to {$data['status']} / {$data['priority']}", $ticket);

        return back()->with('status', 'Ticket updated.');
    }
}
