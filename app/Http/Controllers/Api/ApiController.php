<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shared plumbing for every JSON endpoint.
 *
 * The React client talks to this API exclusively, so the envelope is fixed in
 * one place rather than re-decided per controller:
 *
 *   success  – 200-level responses are `true`, errors are `false`
 *   data     – the payload (API Resources keep Laravel's own `data` key)
 *   message  – human readable text, safe to show in a toast
 *   meta     – request-scoped extras (counts, filters, cache hints)
 *
 * Laravel already renders ValidationException, ModelNotFoundException,
 * AuthenticationException and ThrottleRequestsException as JSON for requests
 * that expectJson(), so those paths are handled by the framework and are not
 * duplicated here.
 */
abstract class ApiController extends Controller
{
    /**
     * Resolve `?per_page=` to a safe page size.
     *
     * Clamped between 1 and config('storefront.per_page_max'): without a
     * ceiling a single `?per_page=100000` would pull the whole catalogue into
     * memory and time the request out.
     */
    protected function perPage(Request $request, ?int $default = null): int
    {
        $max = max(1, (int) config('storefront.per_page_max', 60));
        $default ??= max(1, (int) config('storefront.per_page', 12));

        $requested = (int) $request->integer('per_page', $default);

        return max(1, min($requested, $max));
    }

    /**
     * Standard success envelope for non-resource payloads.
     */
    protected function ok(mixed $data = null, array $meta = [], string $message = 'OK', int $status = Response::HTTP_OK): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'meta' => (object) $meta,
        ], $status);
    }

    /**
     * Standard error envelope.
     *
     * Validation errors keep Laravel's per-field `errors` bag so a React form
     * can map messages straight onto inputs.
     */
    protected function fail(string $message, int $status = Response::HTTP_BAD_REQUEST, array $errors = [], array $meta = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null,
            'errors' => (object) $errors,
            'meta' => (object) $meta,
        ], $status);
    }
}
