<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class SettingsController extends Controller
{
    public function edit(Request $request)
    {
        return view('manage.settings', [
            'values' => [
                'cafe_name'    => config('pos.cafe_name'),
                'till_number'  => config('pos.till_number'),
                'phone'        => config('pos.phone'),
                'thank_you_wa' => config('pos.thank_you_wa'),
            ],
            'hasPassword' => $request->user()->usesPassword(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'cafe_name'    => 'required|string|max:40',
            'till_number'  => ['required', 'string', 'regex:/^[0-9]{4,10}$/'],
            'phone'        => 'nullable|string|max:30',
            'thank_you_wa' => 'nullable|string|max:60',
        ], [
            'till_number.regex' => 'Till number must be 4 to 10 digits, numbers only.',
        ]);

        foreach (Setting::EDITABLE as $key) {
            Setting::put($key, isset($data[$key]) ? trim($data[$key]) : null);
        }

        return redirect()->route('manage.settings')->with('ok', 'Settings saved.');
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'current'  => 'required|string|max:100',
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $currentOk = $user->usesPassword()
            ? Hash::check($data['current'], $user->password)
            : ($user->pin_hash && Hash::check($data['current'], $user->pin_hash));
        if (! $currentOk) {
            return back()->withErrors(['current' => $user->usesPassword() ? 'Your current password is wrong.' : 'Your current PIN is wrong.']);
        }

        $user->forceFill(['password' => $data['password'], 'password_set_at' => now()])->save();

        return redirect()->route('manage.settings')->with('ok', 'Password saved. From now on, sign in with it instead of your PIN.');
    }
}
