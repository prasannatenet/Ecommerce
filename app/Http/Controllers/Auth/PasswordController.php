<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        // Admin accounts must go through the emailed 6-digit code flow on
        // /profile (AdminPasswordController). Allowing the update here would
        // be a one-request bypass of that check.
        $user = $request->user();

        if ($user && ($user->hasRole('admin') || $user->can('access admin'))) {
            return redirect()
                ->route('profile.edit')
                ->with('error', 'Admin passwords can only be changed after the 6-digit code sent to your email is verified.');
        }

        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('status', 'password-updated');
    }
}
