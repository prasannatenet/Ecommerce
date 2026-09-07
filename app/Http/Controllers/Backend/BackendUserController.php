<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class BackendUserController extends Controller
{
    public function index()
    {
        $users = User::paginate(30);
        return view('backend.users.index', compact('users'));
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

    public function destroy(User $user)
    {
        $user->delete();
        return back()->with('success','User removed');
    }
}
