<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\KkEvent;
use App\Models\LegacyBill;
use Illuminate\Http\Request;

/** Old unpaid DineHQ bills: see what each waiter still owes and clear them one by one. */
class LegacyBillsController extends Controller
{
    public function index(Request $request)
    {
        $status = in_array($request->query('status'), ['unpaid', 'mpesa', 'cash', 'written_off', 'all'], true) ? $request->query('status') : 'unpaid';
        $waiter = (string) $request->query('waiter', '');
        $q = LegacyBill::query()->orderByDesc('bill_date')->orderByDesc('bill_no');
        if ($status !== 'all') $q->where('status', $status);
        if ($waiter !== '') $q->where('waiter', $waiter);

        $all = LegacyBill::all();
        $byWaiter = $all->groupBy('waiter')->map(fn ($g) => (object) [
            'bills' => $g->count(), 'total' => $g->sum('amount'),
            'owed' => $g->where('status', 'unpaid')->sum('amount'), 'owedN' => $g->where('status', 'unpaid')->count(),
            'mpesa' => $g->where('status', 'mpesa')->sum('amount'), 'cash' => $g->where('status', 'cash')->sum('amount'),
            'off' => $g->where('status', 'written_off')->sum('amount'),
        ])->sortByDesc('owed');

        return view('manage.legacy-bills', [
            'bills' => $q->limit(400)->get(), 'byWaiter' => $byWaiter, 'status' => $status, 'waiter' => $waiter,
            'waiters' => $all->pluck('waiter')->unique()->sort()->values(),
            'tot' => (object) ['all' => $all->sum('amount'), 'owed' => $all->where('status', 'unpaid')->sum('amount'), 'n' => $all->where('status', 'unpaid')->count()],
        ]);
    }

    public function clear(Request $request, LegacyBill $bill)
    {
        $data = $request->validate([
            'how'  => 'required|in:mpesa,cash,written_off,unpaid',
            'code' => ['nullable', 'required_if:how,mpesa', 'regex:/^[A-Za-z0-9]{8,12}$/'],
            'note' => 'nullable|required_if:how,written_off|string|max:200',
        ], ['code.required_if' => 'Type the M-Pesa code.', 'note.required_if' => 'Say why it is written off.']);

        $upd = ['status' => $data['how'], 'note' => $data['note'] ?? null, 'cleared_by' => $request->user()->id, 'cleared_at' => now(), 'kk_reference' => null, 'verified' => false];
        if ($data['how'] === 'mpesa') {
            $code = strtoupper($data['code']);
            if (LegacyBill::where('kk_reference', $code)->where('id', '!=', $bill->id)->exists() || \App\Models\Payment::where('kk_reference', $code)->exists()) {
                return back()->withErrors(['legacy' => "M-Pesa $code is already used on another bill."]);
            }
            $ev = KkEvent::where('reference', $code)->first();
            if ($ev && (float) $ev->amount + .004 < (float) $bill->amount) {
                return back()->withErrors(['legacy' => "M-Pesa $code was KSh " . number_format((float) $ev->amount) . ', less than this bill (KSh ' . number_format((float) $bill->amount) . ').']);
            }
            $upd['kk_reference'] = $code;
            $upd['verified'] = (bool) $ev;
            $ev?->update(['status' => 'matched']);
        }
        if ($data['how'] === 'unpaid') {
            if ($bill->kk_reference) KkEvent::where('reference', $bill->kk_reference)->where('status', 'matched')->update(['status' => 'pending']);
            $upd = ['status' => 'unpaid', 'note' => null, 'cleared_by' => null, 'cleared_at' => null, 'kk_reference' => null, 'verified' => false];
        }
        $bill->update($upd);

        $what = ['mpesa' => 'paid by M-Pesa ' . ($upd['kk_reference'] ?? '') . ($upd['verified'] ? ' (found in Kopo Kopo)' : ' (not yet in Kopo Kopo records: will be checked against the statement)'), 'cash' => 'paid in cash', 'written_off' => 'written off', 'unpaid' => 'back to NOT PAID'][$data['how']];
        return back()->with('ok', "Old bill {$bill->bill_no} of " . $bill->bill_date->format('j M') . " $what.");
    }
}
