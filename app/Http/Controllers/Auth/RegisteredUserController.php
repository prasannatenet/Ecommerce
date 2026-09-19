<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\WelcomeUserMail;
use App\Models\Slider;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        $registerSliderImages = Slider::query()
            ->where('is_active', true)
            ->whereNotNull('image_path')
            ->where('image_path', '!=', '')
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->pluck('image_path')
            ->map(fn ($path) => asset('storage/' . ltrim($path, '/')))
            ->values();

        return view('auth.register', compact('registerSliderImages'));
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'phone' => ['required', 'string', 'regex:/^[0-9+\-\s()]{7,20}$/', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => preg_replace('/[\s\-\(\)]/', '', $request->phone),
            'password' => Hash::make($request->password),
        ]);

        Role::firstOrCreate(['name' => 'user']);
        $user->syncRoles(['user']);

                event(new Registered($user));

        Auth::login($user);

        if (! empty($user->email)) {
            Mail::to($user->email)->send(new WelcomeUserMail($user));
        }

        return redirect(route('dashboard', absolute: false));
    }
}
