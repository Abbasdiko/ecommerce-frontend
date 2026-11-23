<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContactController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $this->authorizePermission($request, 'contact.view');

        $messages = ContactMessage::query()
            ->with('handler:id,first_name,last_name')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%$s%")->orWhere('email', 'like', "%$s%")->orWhere('subject', 'like', "%$s%")))
            ->latest()
            ->paginate($this->perPage($request));

        return $this->ok([
            'messages' => $messages,
            'summary' => [
                'new' => ContactMessage::where('status', 'new')->count(),
                'total' => ContactMessage::count(),
            ],
        ]);
    }

    public function show(Request $request, ContactMessage $contactMessage)
    {
        $this->authorizePermission($request, 'contact.view');

        if ($contactMessage->status === 'new') {
            $contactMessage->update(['status' => 'read']);
        }

        return $this->ok($contactMessage->load('handler:id,first_name,last_name'));
    }

    public function update(Request $request, ContactMessage $contactMessage)
    {
        $this->authorizePermission($request, 'contact.view');

        $data = $request->validate([
            'status' => ['required', Rule::in(['new', 'read', 'replied', 'closed'])],
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $contactMessage->update($data + [
            'handled_by' => $request->user()->id,
            'handled_at' => now(),
        ]);
        AuditLogger::record('contact.updated', $contactMessage, [], $data);

        return $this->ok($contactMessage->fresh('handler:id,first_name,last_name'), 'Message updated');
    }

    public function destroy(Request $request, ContactMessage $contactMessage)
    {
        $this->authorizePermission($request, 'contact.view');
        $contactMessage->delete();

        return $this->ok(null, 'Message deleted');
    }
}
