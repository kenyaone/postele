<?php

namespace App\Http\Controllers;

use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\StaffClock;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/** Photo clock in / out on the POS screen: name + PIN + a camera snapshot. Marks the attendance register. */
class ClockController extends Controller
{
    public function show()
    {
        $staff = Staff::where('active', true)->orderBy('name')->get(['id', 'name', 'position']);
        $last = StaffClock::whereDate('at', today())->orderBy('at')->get()->groupBy('staff_id')->map(fn ($g) => $g->last());
        return view('clock', compact('staff', 'last'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'staff_id' => 'required|integer',
            'pin'      => 'required|string|max:12',
            'kind'     => 'required|in:in,out',
            'photo'    => ['required', 'string', 'max:2000000', 'regex:/^data:image\/jpeg;base64,/'],
        ]);
        $staff = Staff::where('active', true)->find($data['staff_id']);
        if (! $staff || ! $this->pinOk($staff, $data['pin'])) {
            return back()->withErrors(['pin' => 'Wrong name or PIN.'])->withInput($request->except('pin', 'photo'));
        }
        $jpeg = base64_decode(substr($data['photo'], strlen('data:image/jpeg;base64,')), true);
        if ($jpeg === false || strlen($jpeg) < 2000 || substr($jpeg, 0, 2) !== "\xFF\xD8") {
            return back()->withErrors(['photo' => 'The photo did not come through. Look at the camera and try again.']);
        }
        $now = now();
        $path = 'clock/' . $now->format('Y-m') . '/' . $staff->id . '-' . $now->format('Ymd-His') . '-' . $data['kind'] . '.jpg';
        Storage::disk('local')->put($path, $jpeg);
        StaffClock::create(['staff_id' => $staff->id, 'kind' => $data['kind'], 'at' => $now, 'photo' => $path, 'ip' => $request->ip()]);

        if ($data['kind'] === 'in') {
            $mark = StaffAttendance::firstOrNew(['staff_id' => $staff->id, 'day' => $now->toDateString()]);
            if (! $mark->exists || $mark->status === 'absent') {
                $mark->fill(['status' => 'present', 'note' => $mark->note ?: 'Clocked in ' . $now->format('H:i')])->save();
            }
        }
        $verb = $data['kind'] === 'in' ? 'clocked in' : 'clocked out';
        return redirect()->route('clock')->with('ok', "{$staff->name} {$verb} at " . $now->format('H:i') . '.');
    }

    /** Manager-only: the photo for one clock entry. */
    public function photo(StaffClock $clock)
    {
        abort_unless($clock->photo && Storage::disk('local')->exists($clock->photo), 404);
        return response(Storage::disk('local')->get($clock->photo), 200, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, max-age=3600']);
    }

    private function pinOk(Staff $staff, string $pin): bool
    {
        if ($staff->clock_pin_hash && Hash::check($pin, $staff->clock_pin_hash)) { return true; }
        $user = $staff->user_id ? User::find($staff->user_id) : null;
        return (bool) ($user && $user->pin_hash && Hash::check($pin, $user->pin_hash));
    }
}
