<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Models\AgentDocument;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KycController extends Controller
{
    use ApiResponse;

    private const REQUIRED_TYPES = ['cac', 'id_card', 'proof_of_address'];

    private function agent(Request $request)
    {
        $agent = $request->user()->agent;
        abort_unless($agent, 403, 'Complete your business profile first.');

        return $agent;
    }

    public function show(Request $request)
    {
        $agent = $this->agent($request);
        $docs = $agent->documents()->latest()->get();

        return $this->ok([
            'verification_status' => $agent->verification_status,
            'kyc_submitted_at' => $agent->kyc_submitted_at,
            'rejection_reason' => $agent->rejection_reason,
            'required_types' => self::REQUIRED_TYPES,
            'documents' => $docs->map(fn (AgentDocument $d) => [
                'id' => $d->id,
                'type' => $d->type,
                'status' => $d->status,
                'review_note' => $d->review_note,
                'file_url' => asset('storage/' . $d->file_path),
                'uploaded_at' => $d->created_at,
            ]),
            'can_submit' => $this->hasAllTypes($agent) && ! in_array($agent->verification_status, ['pending', 'verified'], true),
        ]);
    }

    public function upload(Request $request)
    {
        $agent = $this->agent($request);

        $data = $request->validate([
            'type' => ['required', Rule::in(self::REQUIRED_TYPES)],
            'document' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $path = $request->file('document')->store('kyc/' . $agent->id, 'public');

        // Replace an existing document of the same type.
        $agent->documents()->where('type', $data['type'])->delete();

        $doc = $agent->documents()->create([
            'type' => $data['type'],
            'file_path' => $path,
            'status' => 'pending',
        ]);
        AuditLogger::record('kyc.document_uploaded', $doc);

        return $this->created(['id' => $doc->id, 'type' => $doc->type], 'Document uploaded');
    }

    public function submit(Request $request)
    {
        $agent = $this->agent($request);

        if ($agent->verification_status === 'verified') {
            return $this->fail('Your KYC is already verified.', null, 409);
        }
        if (! $this->hasAllTypes($agent)) {
            return $this->fail('Upload all required documents before submitting.', ['required_types' => self::REQUIRED_TYPES], 422);
        }

        $agent->update([
            'verification_status' => 'pending',
            'kyc_submitted_at' => now(),
            'rejection_reason' => null,
        ]);
        $agent->documents()->update(['status' => 'pending']);
        AuditLogger::record('kyc.submitted', $agent);

        return $this->ok(null, 'KYC submitted for review');
    }

    private function hasAllTypes($agent): bool
    {
        $have = $agent->documents()->pluck('type')->unique();

        return collect(self::REQUIRED_TYPES)->every(fn ($t) => $have->contains($t));
    }
}
