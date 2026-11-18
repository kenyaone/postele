<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\StkRequest;
use App\Models\Ticket;
use Illuminate\Http\Request;

/** Phone page: a waiter's unpaid bills, each with a box to send the customer an M-Pesa prompt (order number travels with it). */
class CollectController extends Controller
{
    public function index(Request $request)
    {
        $me = $request->user();

        $bills = Ticket::with(['items.item', 'payments', 'opener'])
            ->whereIn('status', ['open', 'sent'])
            ->where(fn ($q) => $q->whereNull('channel')->orWhere('channel', '!=', 'glovo'))
            ->where('created_at', '>=', now()->subDays(2)->startOfDay())
            ->when($me->role !== 'manager', fn ($q) => $q->where('opened_by', $me->id))
            ->orderByDesc('id')
            ->get()
            ->filter(fn ($t) => $t->balance >= 1)
            ->values();

        $prompts = StkRequest::whereIn('ticket_id', $bills->pluck('id'))
            ->where('created_at', '>=', now()->subMinutes(30))
            ->orderBy('id')->get()->keyBy('ticket_id');

        $waiting = $prompts->contains(fn ($s) => $s->phone !== 'kk-app' && in_array($s->status, ['pending', 'sent'], true));

        return view('pos.collect', compact('bills', 'prompts', 'waiting', 'me'));
    }
}
