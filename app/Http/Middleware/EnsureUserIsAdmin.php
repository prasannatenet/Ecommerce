<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        // allow users with 'admin' role or permission
        if (!Auth::user() || (!Auth::user()->hasRole('admin') && !Auth::user()->can('access admin'))) {
            return redirect()->route('account.index')
                ->with('error', 'You do not have access to the admin panel.');
        }


        return $next($request);
    }
}
