<?php

namespace App\Console\Commands;

use App\Models\KkEvent;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\Ticket;
use App\Models\TicketItem;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class DailySummary extends Command
{
    protected $signature = 'pos:daily-summary {--date= : Day to report (YYYY-MM-DD), default today} {--to= : Send to this address instead}';
    protected $description = 'Email the end-of-day sales summary';

    public function handle(): int
    {
        $day = $this->option('date') ? Carbon::parse($this->option('date')) : today();
        $from = $day->copy()->startOfDay(); $to = $day->copy()->endOfDay();
        $email = $this->option('to') ?: (Setting::where('key', 'report_email')->value('value') ?: null);
        if (! $email) { $this->error('No report_email setting.'); return self::FAILURE; }

        $pays = Payment::with('ticket')->whereBetween('received_at', [$from, $to])->where('method', '!=', 'credit')->get();   // credit is in the debts section
        $group = fn ($m) => str_starts_with($m, 'kk_') ? 'mpesa' : $m;
        $byMethod = $pays->groupBy(fn ($p) => $group($p->method))->map->sum('amount');
        $hasCh = Schema::hasColumn('tickets', 'channel');
        $byChannel = $pays->groupBy(fn ($p) => $hasCh && ($p->ticket->channel ?? 'house') === 'glovo' ? 'Glovo' : 'Dine-in / takeaway')->map->sum('amount');
        $sales = (float) $pays->sum('amount');
        $orders = $pays->pluck('ticket_id')->unique()->count();
        // Sales are the bills OPENED on this day. Payments recorded today can also be for
        // older bills cleared today; those are collections, not today's sales, so they are shown apart.
        $collected = $sales;
        $collectedOrders = $orders;
        $dayBills = Ticket::with('opener')->where('status', '!=', 'void')->whereBetween('opened_at', [$from, $to])->get();
        $sales = (float) $dayBills->sum('total');
        $orders = $dayBills->count();
        $paidOrders = $dayBills->where('status', 'paid')->count();
        $oldPays = $pays->filter(fn ($p) => $p->ticket && $p->ticket->opened_at && $p->ticket->opened_at->lt($from));
        $oldCleared = ['bills' => $oldPays->pluck('ticket_id')->unique()->count(), 'amount' => (float) $oldPays->sum('amount')];
        $mpesaIn = KkEvent::whereBetween('received_at', [$from, $to])->where('amount', '>', 0)
            ->whereNotNull('reference')->where('reference', '!=', '')->get()->unique('reference');
        $late = Payment::where('method', '!=', 'credit')->whereBetween('created_at', [$from, $to])->where('received_at', '<', $from)->get();
        $lateLinked = ['count' => $late->count(), 'amount' => (float) $late->sum('amount')];
        $mpesaReceived = ['count' => $mpesaIn->count(), 'amount' => (float) $mpesaIn->sum('amount')];

        $top = TicketItem::query()->join('tickets', 'tickets.id', '=', 'ticket_items.ticket_id')->join('items', 'items.id', '=', 'ticket_items.item_id')
            ->where('tickets.status', 'paid')->whereBetween('tickets.closed_at', [$from, $to])->whereNull('ticket_items.void_at')
            ->groupBy('items.name')->orderByDesc('qty')->limit(8)
            ->get([DB::raw('items.name as name'), DB::raw('SUM(ticket_items.qty) as qty'), DB::raw('SUM(ticket_items.qty * ticket_items.unit_price) as value')]);

        $voids = Ticket::with('items', 'opener')->where('status', 'void')->whereBetween('updated_at', [$from, $to])->get();
        $openBills = Ticket::with('items', 'opener')->where('status', 'sent')->whereBetween('opened_at', [$from, $to])->get();
        $older = Ticket::where('status', 'sent')->where('opened_at', '<', $from)->get();
        $backlog = ['count' => $older->count(), 'amount' => (float) $older->sum(fn ($t) => $t->balance)];
        $unlinked = KkEvent::where('status', 'pending')->whereBetween('created_at', [$from, $to])->orderBy('id')->get()
            ->filter(fn ($e) => (float) $e->amount > 0 && ! ($e->reference && Payment::where('kk_reference', $e->reference)->exists()))->values();

        $credit = ['given' => 0, 'repaid_cash' => 0, 'repaid' => 0, 'owed' => 0];
        if (Schema::hasTable('credit_sales')) {
            $credit['given'] = (float) DB::table('credit_sales')->whereBetween('created_at', [$from, $to])->sum('amount');
            $credit['repaid'] = (float) DB::table('credit_payments')->whereBetween('received_at', [$from, $to])->sum('amount');
            $credit['repaid_cash'] = (float) DB::table('credit_payments')->where('method', 'cash')->whereBetween('received_at', [$from, $to])->sum('amount');
            $credit['owed'] = (float) \App\Models\Customer::withTotals()->get()->sum(fn ($c) => max(0, $c->balance));
        }
        $cashIn = (float) ($byMethod['cash'] ?? 0) + $credit['repaid_cash'];

        $staff = $pays->groupBy(fn ($p) => $p->ticket->opener->name ?? '?')->map(fn ($g) => ['orders' => $g->pluck('ticket_id')->unique()->count(), 'sales' => $g->sum('amount')])->sortByDesc('sales');

        $staff = $dayBills->groupBy(fn ($t) => $t->opener->name ?? '?')->map(fn ($g) => ['orders' => $g->count(), 'sales' => (float) $g->sum('total')])->sortByDesc('sales');
        $closes = \App\Models\DayClose::with('user:id,name')->whereDate('day', $day)->get();
        $meat = app(\App\Services\MeatCheck::class)->day($day->copy());
        $data = compact('day', 'sales', 'orders', 'byMethod', 'byChannel', 'top', 'voids', 'openBills', 'unlinked', 'credit', 'cashIn', 'staff', 'collected', 'collectedOrders', 'paidOrders', 'oldCleared', 'mpesaReceived', 'backlog', 'lateLinked', 'meat', 'closes');
        $html = view('emails.daily-summary', $data)->render();
        $subject = config('pos.cafe_name', 'Tele Cafe') . ' ' . $day->format('D j M') . ': KSh ' . number_format($sales) . ' sales'
            . ($unlinked->count() ? ', ' . $unlinked->count() . ' M-Pesa not on a bill' : '') . ($openBills->count() ? ', ' . $openBills->count() . ' unpaid' : '');
        Mail::html($html, fn ($m) => $m->to($email)->subject($subject));
        $this->info("Sent to $email: $subject");
        return self::SUCCESS;
    }
}
