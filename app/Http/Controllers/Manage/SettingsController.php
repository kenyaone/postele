<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function edit()
    {
        return view('manage.settings', [
            'values' => [
                'cafe_name'    => config('pos.cafe_name'),
                'till_number'  => config('pos.till_number'),
                'phone'        => config('pos.phone'),
                'thank_you_wa' => config('pos.thank_you_wa'),
            ],
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

        return redirect()->route('manage.settings')->with('ok', 'Settings saved. New bills and receipts will use them.');
    }
}
