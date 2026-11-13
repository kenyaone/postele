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
            'address'      => 'nullable|string|max:80',
            'print_logo'   => 'nullable|in:1',
            'logo'         => 'nullable|file|mimes:png,jpg,jpeg,webp|max:3072',
        ], [
            'till_number.regex' => 'Till number must be 4 to 10 digits, numbers only.',
        ]);

        if ($request->boolean('remove_logo')) { $this->dropLogo(); Setting::put('logo', null); }
        if ($request->hasFile('logo')) {
            $path = $this->saveLogo($request->file('logo')->getRealPath());
            if (! $path) return back()->withInput()->withErrors(['logo' => 'That picture could not be read. Use a PNG or JPG.']);
            $this->dropLogo(); Setting::put('logo', $path);
        }
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

    /** Shrink the uploaded logo to at most 512 px and keep it as PNG in public/branding. */
    private function saveLogo(string $file): ?string
    {
        $src = @imagecreatefromstring((string) file_get_contents($file));
        if (! $src) return null;
        $w = imagesx($src); $h = imagesy($src); $k = min(1, 512 / max($w, $h));
        $nw = max(1, (int) round($w * $k)); $nh = max(1, (int) round($h * $k));
        $img = imagecreatetruecolor($nw, $nh); imagealphablending($img, false); imagesavealpha($img, true);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 255, 255, 255, 127));
        imagecopyresampled($img, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        $name = '/branding/logo-' . time() . '.png';
        if (! is_dir(public_path('branding'))) mkdir(public_path('branding'), 0755, true);
        imagepng($img, public_path(ltrim($name, '/')));
        return $name;
    }

    private function dropLogo(): void
    {
        $old = \App\Models\Setting::where('key', 'logo')->value('value');
        if ($old && str_starts_with($old, '/branding/')) @unlink(public_path(ltrim($old, '/')));
    }
}
