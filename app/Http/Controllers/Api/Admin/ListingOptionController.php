<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ListingOptionController extends Controller
{
    use ApiResponse;

    private const DEFAULTS = [
        'sizes' => ['XS', 'S', 'M', 'L', 'XL', 'XXL'],
        'colors' => ['Black', 'White', 'Red', 'Blue', 'Green'],
    ];

    public function index(Request $request)
    {
        abort_unless($request->user()->can('listing.view') || $request->user()->hasRole('agent'), 403);

        return $this->ok($this->options());
    }

    public function update(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(['admin', 'super-admin']), 403);
        $this->authorizePermission($request, 'listing.update');

        $data = $request->validate([
            'sizes' => ['required', 'array', 'max:100'],
            'sizes.*' => ['required', 'string', 'max:80'],
            'colors' => ['required', 'array', 'max:100'],
            'colors.*' => ['required', 'string', 'max:80'],
        ]);

        foreach (['sizes', 'colors'] as $type) {
            $values = array_map(fn ($value) => trim($value), $data[$type]);
            if (count(array_unique(array_map('mb_strtolower', $values))) !== count($values)) {
                throw ValidationException::withMessages([$type => ['Options must be unique.']]);
            }
            $data[$type] = array_values($values);
        }

        Setting::put('catalog.product_sizes', $data['sizes'], 'catalog');
        Setting::put('catalog.product_colors', $data['colors'], 'catalog');

        return $this->ok($this->options(), 'Product options saved');
    }

    private function options(): array
    {
        return [
            'sizes' => Setting::value('catalog.product_sizes', self::DEFAULTS['sizes']),
            'colors' => Setting::value('catalog.product_colors', self::DEFAULTS['colors']),
        ];
    }
}
