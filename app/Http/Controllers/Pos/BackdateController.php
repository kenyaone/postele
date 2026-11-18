<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Managers only: enter an order after the fact (POS was down, waiter forgot, paper order), dated to when it
 * really happened. It gets that day's order number, is credited to the chosen waiter, prints nothing, and its
 * payment is dated to that time, so daily sales and waiter pay count it on the right day.
 */
class BackdateController extends Controller
{
    public const MAX_DAYS = 14;

    public function create(Request $request)
    {
        $staff = User::where('active', true)->whereIn('role', ['waiter', 'cashier', 'manager'])->orderBy('name')->get(['id', 'name', 'role']);
        $recent = Ticket::with(['items', 'payments', 'opener'])->whereNotNull('backdated_by')->latest('id')->limit(15)->get();
        return view('pos.backdate', ['staff' => $staff, 'recent' => $recent, 'maxDays' => self::MAX_DAYS]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'date' => 'required|date_format:Y-m-d',
            'time' => 'required|date_format:H:i',
            'waiter_id' => 'required|integer|exists:users,id',
            'channel' => 'nullable|in:house,glovo',
            'reason' => 'required|string|max:150',
        ], ['reason.required' => 'Say why this order is being entered late.']);

        $at = Carbon::createFromFormat('Y-m-d H:i', $data['date'] . ' ' . $data['time']);
        if ($at->gt(now())) {
            return back()->withInput()->withErrors(['date' => 'That time is in the future.']);
        }
        if ($at->lt(now()->subDays(self::MAX_DAYS)->startOfDay())) {
            return back()->withInput()->withErrors(['date' => 'Orders can be back-dated at most ' . self::MAX_DAYS . ' days.']);
        }
        $waiter = User::where('id', $data['waiter_id'])->where('active', true)->first();
        if (! $waiter) return back()->withInput()->withErrors(['waiter_id' => 'Choose an active staff member.']);

        $t = new Ticket([
            'opened_by' => $waiter->id,
            'backdated_by' => $request->user()->id,
            'status' => 'open',
            'channel' => ($data['channel'] ?? 'house') === 'glovo' ? 'glovo' : 'house',
            'opened_at' => $at,
            'notes' => 'Back-dated by ' . $request->user()->name . ' on ' . now()->format('D j M H:i') . ': ' . $data['reason'],
        ]);
        $t->created_at = $at; // the order number is taken from this day
        $t->save();

        $request->session()->put('pos_channel', $t->channel);
        return redirect()->route('pos.tickets.show', $t)
            ->with('ok', 'Back-dated order #' . $t->order_no . ' for ' . $waiter->name . ' (' . $at->format('D j M H:i') . '). Add the dishes, press Send, then record the payment. Nothing will print.');
    }
}
