<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $this->authorizePermission($request, 'audit.view');

        $logs = AuditLog::query()
            ->with('user:id,first_name,last_name,email')
            ->when($request->action, fn ($q, $a) => $q->where('action', 'like', "%$a%"))
            ->when($request->entity_type, fn ($q, $e) => $q->where('entity_type', $e))
            ->when($request->user_id, fn ($q, $id) => $q->where('user_id', $id))
            ->latest()
            ->paginate($this->perPage($request));

        return $this->ok($logs);
    }
}
