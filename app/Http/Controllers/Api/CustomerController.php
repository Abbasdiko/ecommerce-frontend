<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\Wallet;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $this->authorizePermission($request, 'customer.view');
        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $customers = User::role('customer')
            ->with('customerProfile')
            ->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q
                ->where('first_name', 'like', "%$s%")
                ->orWhere('last_name', 'like', "%$s%")
                ->orWhere('email', 'like', "%$s%")))
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['date_from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->latest()
            ->paginate($this->perPage($request));

        return UserResource::collection($customers);
    }

    public function show(Request $request, User $customer)
    {
        $this->authorizePermission($request, 'customer.view');
        abort_unless($customer->hasRole('customer'), 404);

        $customer->load('customerProfile', 'addresses');
        $wallet = Wallet::query()
            ->where('user_id', $customer->id)
            ->where('owner_type', 'customer')
            ->first();

        $transactions = $wallet
            ? $wallet->transactions()->paginate($this->perPage($request))
            : null;

        $periodStart = now()->startOfMonth()->subMonths(11);
        $monthlyTransactions = $wallet
            ? $wallet->transactions()
                ->where('created_at', '>=', $periodStart)
                ->reorder()
                ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, direction, SUM(amount) as total')
                ->groupBy('year', 'month', 'direction')
                ->get()
            : collect();

        $reportMonths = collect(range(0, 11))->map(fn (int $offset) => $periodStart->copy()->addMonths($offset));
        $report = [
            'labels' => $reportMonths->map(fn (Carbon $month) => $month->format('M Y'))->all(),
            'credits' => [],
            'debits' => [],
        ];

        foreach ($reportMonths as $month) {
            $credits = $monthlyTransactions->first(fn ($row) => (int) $row->year === (int) $month->year
                && (int) $row->month === (int) $month->month && $row->direction === 'credit');
            $debits = $monthlyTransactions->first(fn ($row) => (int) $row->year === (int) $month->year
                && (int) $row->month === (int) $month->month && $row->direction === 'debit');
            $report['credits'][] = (float) ($credits->total ?? 0);
            $report['debits'][] = (float) ($debits->total ?? 0);
        }

        return $this->ok([
            'customer' => new UserResource($customer),
            'wallet' => $wallet ? [
                'available_balance' => (float) $wallet->available_balance,
                'pending_balance' => (float) $wallet->pending_balance,
                'withdrawn_balance' => (float) $wallet->withdrawn_balance,
                'currency' => $wallet->currency,
            ] : [
                'available_balance' => 0,
                'pending_balance' => 0,
                'withdrawn_balance' => 0,
                'currency' => 'NGN',
            ],
            'transactions' => $transactions,
            'report' => $report,
        ]);
    }

    public function update(Request $request, User $customer)
    {
        $this->authorizePermission($request, 'customer.update');
        abort_unless($customer->hasRole('customer'), 404);

        $data = $request->validate([
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name' => ['sometimes', 'string', 'max:100'],
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($customer->id)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30', Rule::unique('users', 'phone')->ignore($customer->id)],
            'status' => ['sometimes', Rule::in(['active', 'inactive', 'suspended'])],
        ]);

        $old = $customer->only(['first_name', 'last_name', 'email', 'phone', 'status']);
        $customer->fill($data);
        $customer->name = trim($customer->first_name . ' ' . $customer->last_name);
        $customer->save();
        AuditLogger::record('customer.updated', $customer, $old, $customer->only(['first_name', 'last_name', 'email', 'phone', 'status']));

        return $this->ok(new UserResource($customer), 'Customer updated');
    }

    public function destroy(Request $request, User $customer)
    {
        $this->authorizePermission($request, 'customer.delete');
        abort_unless($customer->hasRole('customer'), 404);

        if ($customer->id === $request->user()->id) {
            return $this->fail('You cannot delete your own account.', null, 409);
        }

        $customer->delete();
        AuditLogger::record('customer.deleted', $customer);

        return $this->ok(null, 'Customer deleted');
    }
}
