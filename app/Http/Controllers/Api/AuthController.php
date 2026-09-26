<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Token authentication for the React storefront.
 *
 *   POST /gehna/api/v1/auth/register    create an account, returns a token
 *   POST /gehna/api/v1/auth/login       email + password, returns a token
 *   POST /gehna/api/v1/auth/logout      revoke the token used for this call
 *   POST /gehna/api/v1/auth/logout-all  revoke every device's token
 *   GET  /gehna/api/v1/auth/me          the signed-in customer
 *   PUT  /gehna/api/v1/auth/profile     update name / phone
 *   PUT  /gehna/api/v1/auth/password    change password
 *
 * The client sends `Authorization: Bearer <token>` on every subsequent call.
 * Tokens are Sanctum personal access tokens; each login mints a new one and
 * the React app stores it (localStorage) in place of a session cookie.
 *
 * Rate limiting reuses the storefront's LoginRequest, so the API and the Blade
 * login page share one lockout policy: 5 attempts, then a cool-off. Running
 * two separate counters would let an attacker simply alternate between them.
 */
class AuthController extends ApiController
{
    /**
     * Create an account and issue a token.
     */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:190', 'unique:users,email'],
            // phone carries a unique index, so it has to be validated like one.
            // Without this a duplicate reaches the INSERT and surfaces as an
            // opaque 500 SQL error instead of a field-level 422.
            'phone' => ['nullable', 'string', 'max:32', 'unique:users,phone'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            // The User model casts `password` to `hashed`, so this is stored
            // bcrypt'd even though a plain string is passed in.
            'password' => $data['password'],
            'gehna_coins' => 0,
        ]);

        return $this->ok(
            $this->credentials($user),
            message: 'Registration successful',
            status: Response::HTTP_CREATED,
        );
    }

    /**
     * Exchange email + password for an API token.
     *
     * The credential check is done with Hash::check() rather than
     * Auth::attempt() on purpose: the `api` middleware group carries no
     * session, so logging in through the session guard would write to a
     * session that is never stored.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $request->ensureIsNotRateLimited();

        $user = User::where('email', $request->string('email'))->first();

        if (! $user || ! Hash::check((string) $request->string('password'), $user->password)) {
            RateLimiter::hit($request->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($request->throttleKey());

        return $this->ok($this->credentials($user), message: 'Logged in successfully');
    }

    /**
     * Revoke only the token that made this request.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return $this->ok(null, message: 'Logged out successfully');
    }

    /**
     * Revoke every token for this user ("sign out everywhere").
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return $this->ok(null, message: 'Signed out of all devices');
    }

    /** The signed-in customer; used by the React app to restore a session. */
    public function me(Request $request): JsonResponse
    {
        return $this->ok(new UserResource($request->user()));
    }

    /** Update the signed-in customer's own profile. */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'min:2', 'max:120'],
            // ignore() lets a customer re-save their own profile without the
            // unique check colliding with the phone they already hold.
            'phone' => [
                'sometimes',
                'nullable',
                'string',
                'max:32',
                Rule::unique('users', 'phone')->ignore($user->id),
            ],
        ]);

        $user->fill($data)->save();

        return $this->ok(new UserResource($user->refresh()), message: 'Profile updated');
    }

    /** Change the password; requires the current one to be supplied. */
    public function updatePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'The current password you entered is incorrect.',
            ]);
        }

        $user->password = $data['password'];
        $user->save();

        // Every other device's token is revoked: a password change means anyone
        // holding a stolen token should lose access immediately.
        $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();

        return $this->ok(null, message: 'Password updated');
    }

    /**
     * The `{ token, token_type, user }` body shared by login and register.
     */
    private function credentials(User $user): array
    {
        return [
            'token' => $user->createToken('react-storefront')->plainTextToken,
            'token_type' => 'Bearer',
            'user' => (new UserResource($user))->resolve(),
        ];
    }
}
