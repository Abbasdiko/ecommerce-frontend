<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class CommissionController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $agent = $request->user()->agent;
        abort_unless($agent, 403, 'Complete your business profile first.');

        $base = Commission::where('agent_id', $agent->id);

        $summary = [
            'total_commission' => (float) (clone $base)->whereIn('status', ['pending', 'finalized'])->sum('commission_amount'),
            'finalized' => (float) (clone $base)->where('status', 'finalized')->sum('commission_amount'),
            'pending' => (float) (clone $base)->where('status', 'pending')->sum('commission_amount'),
            'reversed' => (float) (clone $base)->where('status', 'reversed')->sum('commission_amount'),
            'total_earnings' => (float) (clone $base)->whereIn('status', ['pending', 'finalized'])->sum('agent_earning'),
        ];

        $rows = (clone $base)
            ->with(['order:id,order_number', 'agentOrder:id,reference'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate($this->perPage($request));

        return $this->ok(['summary' => $summary, 'commissions' => $rows]);
    }
}
