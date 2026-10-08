<?php

namespace App\Http\Controllers;

use App\Mail\PasswordChangeCodeMail;
use App\Models\PasswordVerificationCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;

/**
 * Two-step admin panel password change on /profile.
 *
 * Step 1  POST /profile/password/code
 *         Validates the current password and the desired new password, stages
 *         the new password hash on a verification row and emails a 6-digit
 *         code. The password does NOT change here.
 *
 * Step 2  POST /profile/password/verify
 *         Accepts the emailed code. Only when it matches an active row does
 *         the staged password get written to the user.
 *
 * The old direct route (PUT /password) is blocked for admin accounts by
 * PasswordController so this flow cannot be sidestepped.
 */
class AdminPasswordController extends Controller
{
    /**
     * Step 1: confirm the current password, then email the 6-digit code.
     */
    public function sendCode(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('passwordChange', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $user = $request->user();
        $code = PasswordVerificationCode::generateCode();

        // One live challenge at a time: a fresh request kills the old code,
        // so a code from a previous attempt can never be replayed later.
        PasswordVerificationCode::where('user_id', $user->id)
            ->where('used', false)
            ->delete();

        $record = PasswordVerificationCode::create([
            'code_hash' => PasswordVerificationCode::hash($code),
            'user_id' => $user->id,
            // Staged here, applied only by verify() below.
            'pending_password_hash' => Hash::make($validated['password']),
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
            'expires_at' => now()->addMinutes(PasswordVerificationCode::EXPIRES_IN_MINUTES),
        ]);

        try {
            Mail::to($user->email)->send(new PasswordChangeCodeMail(
                $user,
                $code,
                PasswordVerificationCode::EXPIRES_IN_MINUTES,
            ));
        } catch (\Throwable $e) {
            // No email, no half-open challenge: let the admin retry cleanly.
            $record->delete();
            report($e);

            return back()->with(
                'error',
                'We could not send the verification email. Please check the mail settings and try again.'
            );
        }

        return back()->with('status', 'password-code-sent');
    }

    /**
     * Step 2: check the emailed code, then apply the staged password.
     */
    public function verify(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('passwordVerify', [
            'code' => ['required', 'digits:6'],
        ]);

        $user = $request->user();

        // Single query so the used/expiry check is atomic: only one request
        // can flip `used` from false to true on the same row.
        $record = PasswordVerificationCode::activeFor($user->id)
            ->latest('id')
            ->first();

        if (! $record
            || ! $record->matches($validated['code'])
            || $record->pending_password_hash === null) {
            return back()->withErrors(
                ['code' => 'That code is incorrect or has expired.'],
                'passwordVerify'
            );
        }

        $record->update([
            'used' => true,
            'used_at' => now(),
        ]);

        $user->update([
            'password' => $record->pending_password_hash,
        ]);

        // Anything still staged on other rows (there should be none) dies
        // with this success so a later visit starts from scratch.
        PasswordVerificationCode::where('user_id', $user->id)
            ->where('used', false)
            ->delete();

        return redirect()->route('profile.edit')
            ->with('status', 'password-updated');
    }

    /**
     * Burn the current challenge and send a fresh code (same staged password).
     */
    public function resend(Request $request): RedirectResponse
    {
        $user = $request->user();

        $record = PasswordVerificationCode::activeFor($user->id)
            ->latest('id')
            ->first();

        if (! $record) {
            return redirect()->route('profile.edit')
                ->with('error', 'No pending verification found. Please start again.');
        }

        $code = PasswordVerificationCode::generateCode();

        $record->update([
            'code_hash' => PasswordVerificationCode::hash($code),
            'expires_at' => now()->addMinutes(PasswordVerificationCode::EXPIRES_IN_MINUTES),
        ]);

        try {
            Mail::to($user->email)->send(new PasswordChangeCodeMail(
                $user,
                $code,
                PasswordVerificationCode::EXPIRES_IN_MINUTES,
            ));
        } catch (\Throwable $e) {
            report($e);

            return back()->with(
                'error',
                'We could not resend the verification email. Please try again shortly.'
            );
        }

        return back()->with('status', 'password-code-sent');
    }

    /**
     * Abandon the pending change entirely (staged password is deleted too).
     */
    public function cancel(Request $request): RedirectResponse
    {
        PasswordVerificationCode::where('user_id', $request->user()->id)
            ->where('used', false)
            ->delete();

        return redirect()->route('profile.edit');
    }
}
