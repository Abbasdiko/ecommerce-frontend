<?php

namespace App\Http\Middleware;

use App\Models\Agent;
use App\Models\Category;
use App\Models\Service;
use App\Services\PlatformSettings;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();

        return array_merge(parent::share($request), [
            'auth' => fn () => [
                'user' => $user ? [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'roles' => $user->getRoleNames()->values(),
                    'permissions' => $user->getAllPermissions()->pluck('name')->values(),
                ] : null,
            ],
            'platform' => fn () => PlatformSettings::publicPayload(),
            'storefrontNavigation' => fn () => [
                'departments' => Category::query()->whereNull('parent_id')
                    ->where('slug', '!=', 'services')
                    ->with(['children' => fn ($query) => $query->orderBy('sort_order')])
                    ->orderBy('sort_order')
                    ->get(['id', 'name', 'slug', 'icon'])
                    ->map(fn (Category $category) => [
                        'id' => (string) $category->id,
                        'name' => $category->name,
                        'slug' => $category->slug,
                        'icon' => $category->icon,
                        'children' => $category->children->map(fn (Category $child) => [
                            'id' => (string) $child->id,
                            'name' => $child->name,
                            'slug' => $child->slug,
                        ])->values(),
                    ])->values(),
                'services' => Service::query()->where('status', 'active')->orderBy('name')
                    ->get(['id', 'name', 'slug']),
                'vendors' => Agent::query()->where('status', 'active')
                    ->orderBy('business_name')
                    ->limit(12)
                    ->get(['id', 'business_name', 'slug'])
                    ->map(fn (Agent $agent) => [
                        'id' => (string) $agent->id,
                        'name' => $agent->business_name,
                        'slug' => $agent->slug,
                    ]),
            ],
            'flash' => fn () => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ]);
    }
}
