<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\User;
use App\Services\Mail\Postman;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupportController extends Controller
{
    public function index(Request $request)
    {
        return view('support.index', [
            'tickets' => $request->user()->tickets()->latest('last_reply_at')->paginate(15),
            'statuses' => Ticket::STATUSES,
        ]);
    }

    public function create()
    {
        return view('support.create', [
            'categories' => Ticket::CATEGORIES,
            'priorities' => Ticket::PRIORITIES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:180'],
            'category' => ['required', Rule::in(array_keys(Ticket::CATEGORIES))],
            'priority' => ['required', Rule::in(array_keys(Ticket::PRIORITIES))],
            'message' => ['required', 'string', 'max:8000'],
        ]);

        $ticket = $request->user()->tickets()->create([
            'subject' => $data['subject'],
            'category' => $data['category'],
            'priority' => $data['priority'],
            'status' => 'open',
            'last_reply_at' => now(),
            'last_reply_by' => 'user',
        ]);
        $ticket->messages()->create([
            'user_id' => $request->user()->id,
            'author_role' => 'user',
            'body' => $data['message'],
            'created_at' => now(),
        ]);

        $postman = app(Postman::class);
        $postman->send('ticket_opened', $request->user()->email, [
            'name' => $request->user()->name, 'ticket_id' => $ticket->id, 'ticket_subject' => $ticket->subject,
            'action_url' => route('support.show', $ticket),
        ]);
        $postman->send('admin_new_ticket', User::where('role', 'admin')->pluck('email')->all(), [
            'customer_name' => $request->user()->name, 'customer_email' => $request->user()->email,
            'ticket_id' => $ticket->id, 'ticket_subject' => $ticket->subject, 'priority' => $ticket->priority,
            'action_url' => route('admin.tickets.show', $ticket),
        ]);

        return redirect()->route('support.show', $ticket)->with('status', 'Ticket opened. Our team will reply shortly.');
    }

    public function show(Request $request, Ticket $ticket)
    {
        abort_unless((int) $ticket->user_id === (int) $request->user()->id, 403);

        return view('support.show', ['ticket' => $ticket->load('messages.user')]);
    }

    public function reply(Request $request, Ticket $ticket)
    {
        abort_unless((int) $ticket->user_id === (int) $request->user()->id, 403);
        $data = $request->validate(['message' => ['required', 'string', 'max:8000']]);

        $ticket->addMessage($data['message'], 'user', $request->user()->id);

        return back()->with('status', 'Reply sent.');
    }

    public function close(Request $request, Ticket $ticket)
    {
        abort_unless((int) $ticket->user_id === (int) $request->user()->id, 403);
        $ticket->update(['status' => 'closed']);

        return back()->with('status', 'Ticket closed.');
    }
}
