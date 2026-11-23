<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\TicketMessage;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TicketController extends Controller
{
    use ApiResponse;

    private function isStaff(Request $request): bool
    {
        return $request->user()->can('ticket.view');
    }

    public function index(Request $request)
    {
        $query = SupportTicket::query()
            ->with(['user:id,first_name,last_name', 'assignee:id,first_name,last_name'])
            ->withCount('messages')
            ->latest('last_reply_at');

        if ($this->isStaff($request)) {
            $query->when($request->status, fn ($q, $s) => $q->where('status', $s))
                ->when($request->priority, fn ($q, $p) => $q->where('priority', $p))
                ->when($request->assigned_to, fn ($q, $id) => $q->where('assigned_to', $id));
        } else {
            $query->where('user_id', $request->user()->id);
        }

        return $this->ok($query->paginate($this->perPage($request)));
    }

    public function show(Request $request, SupportTicket $ticket)
    {
        abort_unless($this->isStaff($request) || $ticket->user_id === $request->user()->id, 403);

        return $this->ok($ticket->load(['messages.user:id,first_name,last_name', 'user:id,first_name,last_name,email', 'assignee:id,first_name,last_name']));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:150'],
            'category' => ['required', Rule::in(['general', 'order', 'payment', 'account', 'technical'])],
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'order_id' => ['nullable', 'exists:orders,id'],
            'message' => ['required', 'string', 'max:4000'],
        ]);

        $ticket = SupportTicket::create([
            'user_id' => $request->user()->id,
            'subject' => $data['subject'],
            'category' => $data['category'],
            'priority' => $data['priority'] ?? 'normal',
            'order_id' => $data['order_id'] ?? null,
            'status' => 'open',
            'last_reply_at' => now(),
        ]);
        $ticket->messages()->create([
            'user_id' => $request->user()->id,
            'is_staff' => false,
            'body' => $data['message'],
        ]);
        AuditLogger::record('ticket.created', $ticket);

        return $this->created($ticket->load('messages.user:id,first_name,last_name'), 'Ticket created');
    }

    public function reply(Request $request, SupportTicket $ticket)
    {
        $staff = $this->isStaff($request);
        abort_unless($staff || $ticket->user_id === $request->user()->id, 403);

        $data = $request->validate(['body' => ['required', 'string', 'max:4000']]);

        $msg = $ticket->messages()->create([
            'user_id' => $request->user()->id,
            'is_staff' => $staff,
            'body' => $data['body'],
        ]);
        $ticket->update([
            'last_reply_at' => now(),
            'status' => $staff ? 'pending' : 'open',
        ]);

        return $this->created($msg->load('user:id,first_name,last_name'), 'Reply sent');
    }

    public function updateStatus(Request $request, SupportTicket $ticket)
    {
        $this->authorizePermission($request, 'ticket.manage');

        $data = $request->validate([
            'status' => ['sometimes', Rule::in(['open', 'pending', 'resolved', 'closed'])],
            'priority' => ['sometimes', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'assigned_to' => ['sometimes', 'nullable', 'exists:users,id'],
        ]);

        $ticket->update($data);
        AuditLogger::record('ticket.updated', $ticket, [], $data);

        return $this->ok($ticket->fresh(['assignee:id,first_name,last_name']), 'Ticket updated');
    }
}
