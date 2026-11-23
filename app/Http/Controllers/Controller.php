<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    protected function authorizePermission(Request $request, string $permission): void
    {
        $user = $request->user();

        if (! $user || ! $user->can($permission)) {
            throw new AccessDeniedHttpException('You do not have permission to perform this action.');
        }
    }

    protected function perPage(Request $request, int $default = 20, int $max = 100): int
    {
        return (int) min($max, max(1, (int) $request->input('per_page', $default)));
    }
}
