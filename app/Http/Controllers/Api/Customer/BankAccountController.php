<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class BankAccountController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        return $this->ok($request->user()->bankAccounts()->orderByDesc('is_default')->get());
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $account = $request->user()->bankAccounts()->create($data);
        $this->syncDefault($request, $account);

        return $this->created($account->fresh(), 'Bank account added');
    }

    public function update(Request $request, BankAccount $bankAccount)
    {
        abort_unless($bankAccount->user_id === $request->user()->id, 403);
        $bankAccount->update($this->validated($request, true));
        $this->syncDefault($request, $bankAccount);

        return $this->ok($bankAccount->fresh(), 'Bank account updated');
    }

    public function destroy(Request $request, BankAccount $bankAccount)
    {
        abort_unless($bankAccount->user_id === $request->user()->id, 403);
        $bankAccount->delete();

        return $this->ok(null, 'Bank account removed');
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $rule = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'bank_name' => [$rule, 'string', 'max:120'],
            'bank_code' => ['nullable', 'string', 'max:10'],
            'account_name' => [$rule, 'string', 'max:120'],
            'account_number' => [$rule, 'string', 'regex:/^\d{10}$/'],
            'is_default' => ['boolean'],
        ]);
    }

    private function syncDefault(Request $request, BankAccount $account): void
    {
        if (! $account->is_default) {
            return;
        }
        $request->user()->bankAccounts()->whereKeyNot($account->id)->update(['is_default' => false]);
    }
}
