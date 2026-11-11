<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class PinLoginController extends Controller
{
    public function show()
    {
        $users = User::where('active', true)
            ->orderByRaw("FIELD(role, 'manager', 'cashier', 'waiter')")
            ->orderBy('name')
            ->get(['id', 'name', 'role']);

        return view('auth.pin-login', compact('users'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'pin'     => 'required|string|min:4|max:10',
        ]);

        $user = User::find($data['user_id']);

        if (! $user || ! $user->active || ! $user->pin_hash || ! Hash::check($data['pin'], $user->pin_hash)) {
            return back()
                ->withInput($request->only('user_id'))
                ->withErrors(['pin' => 'Invalid PIN.']);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
