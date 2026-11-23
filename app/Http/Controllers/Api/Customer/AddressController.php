<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AddressController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        return $this->ok(
            $request->user()->addresses()->orderByDesc('is_default')->orderBy('type')->get()
        );
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $address = $request->user()->addresses()->create($data);
        $this->syncDefault($request, $address);

        return $this->created($address->fresh(), 'Address added');
    }

    public function update(Request $request, Address $address)
    {
        abort_unless($address->user_id === $request->user()->id, 403);

        $address->update($this->validated($request, true));
        $this->syncDefault($request, $address);

        return $this->ok($address->fresh(), 'Address updated');
    }

    public function destroy(Request $request, Address $address)
    {
        abort_unless($address->user_id === $request->user()->id, 403);
        $address->delete();

        return $this->ok(null, 'Address removed');
    }

    public function setDefault(Request $request, Address $address)
    {
        abort_unless($address->user_id === $request->user()->id, 403);
        $address->update(['is_default' => true]);
        $this->syncDefault($request, $address);

        return $this->ok($address->fresh(), 'Default address updated');
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $rule = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'type' => [$rule, Rule::in(['shipping', 'billing'])],
            'label' => ['nullable', 'string', 'max:60'],
            'contact_name' => [$rule, 'string', 'max:120'],
            'contact_phone' => [$rule, 'string', 'max:30'],
            'line1' => [$rule, 'string', 'max:180'],
            'line2' => ['nullable', 'string', 'max:180'],
            'city' => [$rule, 'string', 'max:80'],
            'state' => [$rule, 'string', 'max:80'],
            'lga' => ['nullable', 'string', 'max:80'],
            'country' => ['nullable', 'string', 'max:80'],
            'is_default' => ['boolean'],
        ]);
    }

    private function syncDefault(Request $request, Address $address): void
    {
        if (! $address->is_default) {
            return;
        }

        DB::transaction(function () use ($request, $address) {
            $request->user()->addresses()
                ->where('type', $address->type)
                ->whereKeyNot($address->id)
                ->update(['is_default' => false]);
        });
    }
}
