<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Models\CommissionRule;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CommissionController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $this->authorizePermission($request, 'commission.view');

        $base = Commission::query();

        $summary = [
            'total' => (float) (clone $base)->whereIn('status', ['pending', 'finalized'])->sum('commission_amount'),
            'finalized' => (float) (clone $base)->where('status', 'finalized')->sum('commission_amount'),
            'pending' => (float) (clone $base)->where('status', 'pending')->sum('commission_amount'),
            'reversed' => (float) (clone $base)->where('status', 'reversed')->sum('commission_amount'),
        ];

        $rows = (clone $base)
            ->with(['agent:id,business_name', 'order:id,order_number'])
            ->when($request->agent_id, fn ($q, $id) => $q->where('agent_id', $id))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate($this->perPage($request));

        return $this->ok(['summary' => $summary, 'commissions' => $rows]);
    }

    public function rules(Request $request)
    {
        $this->authorizePermission($request, 'commission.view');

        return $this->ok(CommissionRule::latest()->get());
    }

    public function storeRule(Request $request)
    {
        $this->authorizePermission($request, 'commission.update');

        $data = $this->validateRule($request);
        $rule = CommissionRule::create($data);
        AuditLogger::record('commission_rule.created', $rule);

        return $this->created($rule, 'Commission rule created');
    }

    public function updateRule(Request $request, CommissionRule $commissionRule)
    {
        $this->authorizePermission($request, 'commission.update');

        $commissionRule->update($this->validateRule($request, true));
        AuditLogger::record('commission_rule.updated', $commissionRule);

        return $this->ok($commissionRule->fresh(), 'Commission rule updated');
    }

    public function destroyRule(Request $request, CommissionRule $commissionRule)
    {
        $this->authorizePermission($request, 'commission.update');
        $commissionRule->delete();
        AuditLogger::record('commission_rule.deleted', $commissionRule);

        return $this->ok(null, 'Commission rule deleted');
    }

    private function validateRule(Request $request, bool $partial = false): array
    {
        $rule = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'scope_type' => [$rule, Rule::in(['global', 'service', 'category', 'agent'])],
            'scope_id' => ['nullable', 'integer'],
            'commission_type' => [$rule, Rule::in(['percentage', 'fixed'])],
            'commission_value' => [$rule, 'numeric', 'min:0'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after:effective_from'],
        ]);
    }
}
