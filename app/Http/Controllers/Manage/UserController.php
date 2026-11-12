<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Managers add and edit POS sign-ins (cashier, waiter, stock, manager). */
class UserController extends Controller
{
    public const ROLES = ['cashier' => 'Cashier', 'waiter' => 'Waiter', 'stock' => 'Stock / kitchen', 'manager' => 'Manager'];

    public function index()
    {
        return view('manage.users.index', [
            'users' => User::orderByDesc('active')->orderByRaw("FIELD(role, 'manager', 'cashier', 'waiter', 'stock')")->orderBy('name')->get(),
            'roles' => self::ROLES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:60|unique:users,name',
            'role' => ['required', Rule::in(array_keys(self::ROLES))],
            'pin'  => 'required|digits_between:4,6|confirmed',
        ], ['name.unique' => 'Someone already has that name; add a surname or initial.']);

        User::create([
            'name'     => trim($data['name']),
            'email'    => Str::slug($data['name']) . '-' . Str::lower(Str::random(6)) . '@users.pos.local',
            'password' => Str::random(40),
            'role'     => $data['role'],
            'pin_hash' => Hash::make($data['pin']),
            'active'   => true,
        ]);

        return redirect()->route('manage.users.index')->with('ok', trim($data['name']) . ' added. They can sign in with their PIN now.');
    }

    public function edit(User $user)
    {
        return view('manage.users.edit', ['user' => $user, 'roles' => self::ROLES, 'isSelf' => $user->id === auth()->id()]);
    }

    public function update(Request $request, User $user)
    {
        $isSelf = $user->id === $request->user()->id;
        $data = $request->validate([
            'name'         => ['required', 'string', 'max:60', Rule::unique('users', 'name')->ignore($user->id)],
            'role'         => ['required', Rule::in(array_keys(self::ROLES))],
            'active'       => 'nullable|boolean',
            'pin'          => 'nullable|digits_between:4,6|confirmed',
            'reset_to_pin' => 'nullable|boolean',
        ]);
        $active = (bool) ($data['active'] ?? false);

        if ($isSelf && ($data['role'] !== $user->role || ! $active)) {
            return back()->withInput()->withErrors(['role' => 'You cannot change your own role or deactivate yourself.']);
        }
        $losingManager = $user->role === 'manager' && $user->active && ($data['role'] !== 'manager' || ! $active);
        if ($losingManager && User::where('role', 'manager')->where('active', true)->count() <= 1) {
            return back()->withInput()->withErrors(['role' => 'This is the only active manager. Add another manager first.']);
        }
        if (! empty($data['reset_to_pin']) && empty($data['pin'])) {
            return back()->withInput()->withErrors(['pin' => 'Enter a new PIN to switch this manager back to PIN sign-in.']);
        }

        $user->name   = trim($data['name']);
        $user->role   = $data['role'];
        $user->active = $active;
        if (! empty($data['pin'])) {
            $user->pin_hash = Hash::make($data['pin']);
        }
        if (! empty($data['reset_to_pin']) || $data['role'] !== 'manager') {
            $user->password_set_at = null;   // signs in with the PIN again
        }
        $user->save();

        return redirect()->route('manage.users.index')->with('ok', "{$user->name} saved.");
    }
}
