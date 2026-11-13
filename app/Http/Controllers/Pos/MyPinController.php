<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/** Any signed-in staff member changes their own sign-in PIN. */
class MyPinController extends Controller
{
    public function edit(Request $request)
    {
        return view('pos.my-pin', ['usesPassword' => $request->user()->usesPassword()]);
    }

    public function update(Request $request)
    {
        $u = $request->user();
        if ($u->usesPassword()) return redirect()->route('pos.pin')->withErrors(['current' => 'You sign in with a password. Change it in Settings.']);
        $d = $request->validate([
            'current' => 'required|string|max:20',
            'pin' => 'required|digits_between:4,6|confirmed',
        ], [
            'pin.digits_between' => 'The new PIN must be 4 to 6 numbers.',
            'pin.confirmed' => 'The two new PINs do not match. Type the same PIN twice.',
        ]);
        if (! $u->pin_hash || ! Hash::check($d['current'], $u->pin_hash)) {
            return back()->withErrors(['current' => 'That is not your current PIN.']);
        }
        if (Hash::check($d['pin'], $u->pin_hash)) {
            return back()->withErrors(['pin' => 'Choose a PIN different from your current one.']);
        }
        if (self::weak($d['pin'])) {
            return back()->withErrors(['pin' => 'That PIN is too easy to guess (like 1234 or 1111). Choose another.']);
        }
        $u->forceFill(['pin_hash' => Hash::make($d['pin'])])->save();
        return redirect()->route('pos.pin')->with('ok', 'Your PIN has been changed. Use the new PIN next time you sign in.');
    }

    private static function weak(string $p): bool
    {
        return count(array_unique(str_split($p))) === 1 || str_contains('01234567890', $p) || str_contains('09876543210', $p);
    }
}
