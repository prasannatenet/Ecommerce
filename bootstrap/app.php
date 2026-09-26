<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /*
        |----------------------------------------------------------------------
        | One error envelope for the whole JSON API
        |----------------------------------------------------------------------
        |
        | The controllers answer with { success, message, data, meta }, but the
        | framework's own failures (401, 404, 422, 429) were rendering in their
        | own shapes: a bare { message } for 401, { message, errors } for 422.
        | A React client would then need two error paths, and the documented
        | contract would be a lie.
        |
        | This normalises them. Non-API requests return null so the default
        | behaviour is untouched: a web form still gets its redirect and flash
        | errors exactly as before.
        |
        */

        $exceptions->render(function (Throwable $e, Request $request): ?JsonResponse {
            if (! $request->is('api/*')) {
                return null;
            }

            // ── 422 validation ──────────────────────────────────
            if ($e instanceof ValidationException) {
                return new JsonResponse([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'data' => null,
                    'errors' => (object) $e->errors(),
                    'meta' => (object) [],
                ], $e->status);
            }

            // ── 401 unauthenticated ─────────────────────────────
            if ($e instanceof AuthenticationException) {
                return new JsonResponse([
                    'success' => false,
                    'message' => $e->getMessage() ?: 'Unauthenticated.',
                    'data' => null,
                    'errors' => (object) [],
                    'meta' => (object) [],
                ], 401);
            }

            // ── 403 forbidden ───────────────────────────────────
            if ($e instanceof AuthorizationException) {
                return new JsonResponse([
                    'success' => false,
                    'message' => $e->getMessage() ?: 'This action is unauthorized.',
                    'data' => null,
                    'errors' => (object) [],
                    'meta' => (object) [],
                ], 403);
            }

            // ── 404 not found ───────────────────────────────────
            // ModelNotFoundException has no status of its own; the HTTP layer
            // turns it into a 404. Handled here so a missing row and a missing
            // route come back identically.
            if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Resource not found.',
                    'data' => null,
                    'errors' => (object) [],
                    'meta' => (object) [],
                ], 404);
            }

            // ── 429 rate limited ────────────────────────────────
            if ($e instanceof TooManyRequestsHttpException) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Too many requests. Please slow down and try again shortly.',
                    'data' => null,
                    'errors' => (object) [],
                    'meta' => (object) [
                        'retry_after' => (int) ($e->getHeaders()['Retry-After'] ?? 60),
                    ],
                ], 429);
            }

            // ── Any other HTTP status (403, 409, 5xx…) ─────────
            if ($e instanceof HttpExceptionInterface) {
                $status = $e->getStatusCode();

                return new JsonResponse([
                    'success' => false,
                    'message' => $e->getMessage() ?: 'Request failed.',
                    'data' => null,
                    'errors' => (object) [],
                    'meta' => (object) [],
                ], $status);
            }

            // ── Anything else is a bug: never leak internals ────
            return new JsonResponse([
                'success' => false,
                'message' => config('app.debug')
                    ? $e->getMessage()
                    : 'Server error. Please try again.',
                'data' => null,
                'errors' => (object) [],
                'meta' => (object) [],
            ], 500);
        });
    })->create();
