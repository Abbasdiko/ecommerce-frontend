<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServicePackageResource;
use App\Models\Service;
use App\Models\ServicePackage;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServicePackageController extends Controller
{
    use ApiResponse;

    public function index(Request $request, Service $service)
    {
        $this->authorizePermission($request, 'package.view');

        return $this->ok(
            ServicePackageResource::collection($service->packages()->orderBy('price')->get())
        );
    }

    public function store(Request $request, Service $service)
    {
        $this->authorizePermission($request, 'package.create');

        $data = $this->validatePackage($request);
        $package = $service->packages()->create($data);
        AuditLogger::record('package.created', $package);

        return $this->created(new ServicePackageResource($package), 'Package created');
    }

    public function update(Request $request, Service $service, ServicePackage $package)
    {
        $this->authorizePermission($request, 'package.update');
        abort_unless($package->service_id === $service->id, 404);

        $package->update($this->validatePackage($request, true));
        AuditLogger::record('package.updated', $package);

        return $this->ok(new ServicePackageResource($package), 'Package updated');
    }

    public function destroy(Request $request, Service $service, ServicePackage $package)
    {
        $this->authorizePermission($request, 'package.delete');
        abort_unless($package->service_id === $service->id, 404);

        if ($package->subscriptions()->exists()) {
            return $this->fail('Cannot delete a package that has subscriptions.', null, 409);
        }

        $package->delete();
        AuditLogger::record('package.deleted', $package);

        return $this->ok(null, 'Package deleted');
    }

    private function validatePackage(Request $request, bool $partial = false): array
    {
        $rule = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$rule, 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'price' => [$rule, 'numeric', 'min:0'],
            'duration_days' => [$rule, 'integer', 'min:1', 'max:3650'],
            'listing_limit' => [$rule, 'integer', 'min:0', 'max:100000'],
            'featured_listing_limit' => ['nullable', 'integer', 'min:0'],
            'commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);
    }
}
