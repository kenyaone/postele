<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** Uncleared (unpaid) bills, and cleared bills with M-Pesa codes and Kopo Kopo verification. */
class BillsController extends Controller
{
    public function index(Request $request)
    {
        $today = now()->startOfDay();
        try {
            $date = $request->filled('date') ? Carbon::createFromFormat('Y-m-d', (string) $request->query('date'))->startOfDay() : $today;
        } catch (\Throwable $e) {
            $date = $today;
        }
        if ($date->gt($today)) {
            $date = $today;
        }
        $code = strtoupper(trim(preg_replace('/[^A-Za-z0-9]/', '', (string) $request->query('code', ''))));

        $open = Ticket::with(['items', 'opener'])->whereIn('status', ['open', 'sent'])->orderBy('opened_at')->get();

        $paid = Ticket::with(['items', 'opener', 'payments'])->where('status', 'paid');
        if ($code !== '') {
            $paid->whereHas('payments', fn ($q) => $q->where('kk_reference', 'like', '%' . $code . '%'));
        } else {
            $paid->whereHas('payments', fn ($q) => $q->whereBetween('received_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()]));
        }
        $paid = $paid->orderByDesc('id')->limit(300)->get();

        return view('pos.bills', [
            'open'       => $open,
            'paid'       => $paid,
            'date'       => $date,
            'code'       => $code,
            'canOpenAll' => $request->user()->role === 'manager',
            'userId'     => $request->user()->id,
        ]);
    }
}
