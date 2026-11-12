<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Cashiers and waiters sign in with a PIN.
 * Managers sign in with a password once they have set one (Manage > Settings);
 * until then their PIN still works, so nobody is locked out.
 */
class PinLoginController extends Controller
{
    public function show()
    {
        $users = User::where('active', true)
            ->orderByRaw("FIELD(role, 'manager', 'cashier', 'waiter')")
            ->orderBy('name')
            ->get(['id', 'name', 'role', 'password_set_at']);

        return view('auth.pin-login', compact('users'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'secret'  => 'required|string|max:100',
        ]);

        $user = User::find($data['user_id']);
        $ok = false;

        if ($user && $user->active) {
            $ok = $user->usesPassword()
                ? Hash::check($data['secret'], $user->password)
                : ($user->pin_hash && Hash::check($data['secret'], $user->pin_hash));
        }

        if (! $ok) {
            return back()
                ->withInput($request->only('user_id'))
                ->withErrors(['secret' => $user && $user->usesPassword() ? 'Wrong password.' : 'Wrong PIN.']);
        }

        Auth::login($user);
        $request->session()->regenerate();

        $redirect = redirect()->intended(route('home'));
        if ($user->role === 'manager' && ! $user->usesPassword()) {
            $redirect->with('warn', 'Please set a manager password in Settings. Once it is set, your PIN will stop working for sign-in.');
        }
        return $redirect;
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
