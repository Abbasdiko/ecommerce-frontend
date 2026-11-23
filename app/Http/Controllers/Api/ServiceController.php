<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServiceController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $this->authorizePermission($request, 'service.view');

        $services = Service::query()
            ->withCount('packages')
            ->when($request->boolean('with_packages'), fn ($q) => $q->with('packages'))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderBy('name')
            ->paginate($this->perPage($request));

        return ServiceResource::collection($services);
    }

    public function show(Request $request, Service $service)
    {
        $this->authorizePermission($request, 'service.view');

        return $this->ok(new ServiceResource($service->load('packages')->loadCount('packages')));
    }

    public function store(Request $request)
    {
        $this->authorizePermission($request, 'service.create');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'description' => ['nullable', 'string', 'max:2000'],
            'service_type' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'requires_approval' => ['boolean'],
        ]);

        $service = Service::create($data);
        AuditLogger::record('service.created', $service);

        return $this->created(new ServiceResource($service), 'Service created');
    }

    public function update(Request $request, Service $service)
    {
        $this->authorizePermission($request, 'service.update');

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:190'],
            'description' => ['nullable', 'string', 'max:2000'],
            'service_type' => ['nullable', 'string', 'max:100'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'requires_approval' => ['boolean'],
        ]);

        $service->update($data);
        AuditLogger::record('service.updated', $service);

        return $this->ok(new ServiceResource($service), 'Service updated');
    }

    public function destroy(Request $request, Service $service)
    {
        $this->authorizePermission($request, 'service.delete');

        if ($service->packages()->whereHas('subscriptions')->exists()) {
            return $this->fail('Cannot delete a service that has active subscriptions.', null, 409);
        }

        $service->delete();
        AuditLogger::record('service.deleted', $service);

        return $this->ok(null, 'Service deleted');
    }
}
