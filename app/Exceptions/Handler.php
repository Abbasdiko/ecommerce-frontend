<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    public function render($request, Throwable $e)
    {
        if (! $request->is('api/*') && ! $request->expectsJson()) {
            return parent::render($request, $e);
        }

        return match (true) {
            $e instanceof ValidationException => response()->json([
                'success' => false,
                'message' => 'The given data was invalid.',
                'errors' => $e->errors(),
            ], 422),

            $e instanceof AuthenticationException => $this->apiError('Unauthenticated.', 401),

            $e instanceof AuthorizationException => $this->apiError('This action is unauthorized.', 403),

            $e instanceof ModelNotFoundException,
            $e instanceof NotFoundHttpException => $this->apiError('Resource not found.', 404),

            $e instanceof HttpExceptionInterface => $this->apiError(
                $e->getMessage() ?: 'Request failed.',
                $e->getStatusCode()
            ),

            default => $this->apiError(
                config('app.debug') ? $e->getMessage() : 'Unable to complete your request. Please try again.',
                500,
                config('app.debug') ? ['exception' => class_basename($e), 'file' => $e->getFile(), 'line' => $e->getLine()] : null
            ),
        };
    }

    protected function apiError(string $message, int $status, ?array $errors = null)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors ?? (object) [],
        ], $status);
    }
}
