<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class BackendUserController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $roleFilter = in_array($request->query('role'), ['all', 'user', 'admin'], true)
            ? (string) $request->query('role', 'all')
            : 'all';

        $query = User::query()
            ->with('roles')
            ->withCount('orders')
            ->latest('id');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($roleFilter === 'admin') {
            $query->role('admin');
        } elseif ($roleFilter === 'user') {
            $query->role('user');
        }

        $users = $query->paginate(20)->withQueryString();

        $stats = [
            'total' => User::count(),
            'customers' => User::role('user')->count(),
            'admins' => User::role('admin')->count(),
            'with_coins' => User::where('gehna_coins', '>', 0)->count(),
        ];

        return view('backend.users.index', [
            'users' => $users,
            'search' => $search,
            'roleFilter' => $roleFilter,
            'stats' => $stats,
        ]);
    }

    public function edit(User $user)
    {
        return view('backend.users.edit', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $data = $request->only($user->getFillable());
        $user->update($data);
        return redirect()->route('admin.users.index')->with('success','User updated');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === (int) Auth::id() || $user->hasRole('admin')) {
            return back()->with('error', 'Admin accounts cannot be removed from here.');
        }

        $user->delete();

        return back()->with('success', 'User removed');
    }
}
