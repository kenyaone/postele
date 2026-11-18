<?php

namespace App\Services;

use App\Models\CreditPayment;
use App\Models\CreditSale;
use App\Models\Payment;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;

/**
 * Waiter pay. Counts only money a waiter actually collected each day:
 *  - cash and M-Pesa paid on bills they opened, received while their shift was open;
 *  - old credit repaid: each repayment pays the customer's oldest credit bills first, and counts
 *    for the waiter of each of those bills on the day it was repaid.
 * Unpaid, cancelled, credit (until repaid) and Glovo-account bills count for nothing.
 *
 * Daily pay is earned when the day's collections reach the daily-pay target. Commission is a
 * percentage of the part of the day's collections above the commission start, paid only when the
 * whole cafe collected at least the cafe gate that day. Saturdays have their own figures.
 * All figures are set in Manage > Settings > Waiter pay.
 */
class WaiterPay
{
    public const DEFAULTS = [
        'pay_daily' => 240,
        'pay_threshold' => 2000, 'pay_threshold_sat' => 1200,
        'pay_comm_pct' => 5,
        'pay_comm_from' => 3000, 'pay_comm_from_sat' => 2000,
        'pay_gate' => 9700, 'pay_gate_sat' => 6000,
        'pay_start' => '2026-11-18',
    ];

    public static function setting(string $key)
    {
        $v = config('pos.' . $key);
        return ($v === null || $v === '') ? self::DEFAULTS[$key] : $v;
    }

    /** The rules that apply on one day. */
    public function rules(Carbon $day): array
    {
        $sat = $day->isSaturday();
        return [
            'daily'     => (float) self::setting('pay_daily'),
            'threshold' => (float) self::setting($sat ? 'pay_threshold_sat' : 'pay_threshold'),
            'pct'       => (float) self::setting('pay_comm_pct'),
            'from'      => (float) self::setting($sat ? 'pay_comm_from_sat' : 'pay_comm_from'),
            'gate'      => (float) self::setting($sat ? 'pay_gate_sat' : 'pay_gate'),
            'active'    => $day->toDateString() >= (string) self::setting('pay_start'),
        ];
    }

    /** Money collected between two dates: ['cafe' => [date => total], 'waiter' => [date => [user_id => total]]]. */
    public function collections(Carbon $from, Carbon $to): array
    {
        $fromDt = $from->copy()->startOfDay();
        $toDt = $to->copy()->endOfDay();
        $cafe = [];
        $waiter = [];

        // 1. Cash and M-Pesa paid on bills (credit and Glovo's account are "credit" until repaid).
        $rows = Payment::join('tickets', 'payments.ticket_id', '=', 'tickets.id')
            ->where('payments.method', '!=', 'credit')
            ->whereBetween('payments.received_at', [$fromDt, $toDt])
            ->get(['payments.amount', 'payments.received_at', 'tickets.opened_by']);
        $shifts = Shift::whereIn('user_id', $rows->pluck('opened_by')->filter()->unique()->values())
            ->where('opened_at', '<=', $toDt)
            ->where(fn ($q) => $q->whereNull('closed_at')->orWhere('closed_at', '>=', $fromDt))
            ->get(['user_id', 'opened_at', 'closed_at'])->groupBy('user_id');
        foreach ($rows as $r) {
            $at = Carbon::parse($r->received_at);
            $d = $at->toDateString();
            $amount = (float) $r->amount;
            $cafe[$d] = ($cafe[$d] ?? 0) + $amount;
            $uid = (int) $r->opened_by;
            $onShift = $uid && ($shifts[$uid] ?? collect())->contains(
                fn ($s) => $s->opened_at->lte($at) && (! $s->closed_at || $s->closed_at->gte($at))
            );
            if ($onShift) {
                $waiter[$d][$uid] = ($waiter[$d][$uid] ?? 0) + $amount;
            }
        }

        // 2. Old credit repaid (not delivery platforms such as Glovo).
        $customers = CreditPayment::whereBetween('received_at', [$fromDt, $toDt])
            ->whereHas('customer', fn ($q) => $q->where('is_platform', false))
            ->pluck('customer_id')->unique();
        foreach ($customers as $cid) {
            $sales = CreditSale::join('tickets', 'credit_sales.ticket_id', '=', 'tickets.id')
                ->where('credit_sales.customer_id', $cid)
                ->orderBy('credit_sales.created_at')->orderBy('credit_sales.id')
                ->get(['credit_sales.amount', 'tickets.opened_by'])
                ->map(fn ($s) => ['left' => (float) $s->amount, 'uid' => (int) $s->opened_by])->all();
            $i = 0;
            $repayments = CreditPayment::where('customer_id', $cid)->where('received_at', '<=', $toDt)
                ->orderBy('received_at')->orderBy('id')->get();
            foreach ($repayments as $p) {
                $inRange = $p->received_at->gte($fromDt);
                $d = $p->received_at->toDateString();
                $counts = in_array($p->method, ['cash', 'mpesa'], true);
                $money = (float) $p->amount;
                if ($inRange && $counts) {
                    $cafe[$d] = ($cafe[$d] ?? 0) + $money;
                }
                // The money pays the oldest bills first; any fees (not money in hand) are used up after it.
                foreach ([[$money, $counts], [(float) $p->fees, false]] as [$left, $countIt]) {
                    while ($left > 0.004 && $i < count($sales)) {
                        $take = min($left, $sales[$i]['left']);
                        $sales[$i]['left'] -= $take;
                        $left -= $take;
                        if ($inRange && $countIt && $sales[$i]['uid']) {
                            $waiter[$d][$sales[$i]['uid']] = ($waiter[$d][$sales[$i]['uid']] ?? 0) + $take;
                        }
                        if ($sales[$i]['left'] <= 0.004) $i++;
                    }
                }
            }
        }

        return ['cafe' => $cafe, 'waiter' => $waiter];
    }

    /** Daily pay and commission for one waiter-day. */
    public function pay(float $collected, float $cafe, array $r): array
    {
        $daily = $r['active'] && $collected + 0.004 >= $r['threshold'] ? $r['daily'] : 0.0;
        $commission = $r['active'] && $cafe + 0.004 >= $r['gate'] && $collected > $r['from']
            ? round(($collected - $r['from']) * $r['pct'] / 100, 2) : 0.0;
        return ['collected' => round($collected, 2), 'daily' => $daily, 'commission' => $commission, 'total' => round($daily + $commission, 2)];
    }

    /** One row per waiter per day: name, date, collected, daily, commission, total, cafe (the cafe's total that day). */
    public function days(Carbon $from, Carbon $to): array
    {
        $c = $this->collections($from, $to);
        $waiters = User::where('role', 'waiter')->pluck('name', 'id');
        $out = [];
        for ($d = $from->copy()->startOfDay(); $d->lte($to); $d->addDay()) {
            $k = $d->toDateString();
            $rules = $this->rules($d);
            $cafe = round((float) ($c['cafe'][$k] ?? 0), 2);
            foreach ($c['waiter'][$k] ?? [] as $uid => $amount) {
                if (! isset($waiters[$uid])) continue; // managers and cashiers are not on waiter pay
                $out[] = ['user_id' => $uid, 'name' => $waiters[$uid], 'date' => $k, 'cafe' => $cafe] + $this->pay((float) $amount, $cafe, $rules);
            }
        }
        usort($out, fn ($a, $b) => [$a['name'], $a['date']] <=> [$b['name'], $b['date']]);
        return $out;
    }

    /** Totals per waiter for the dates given, plus the day rows. */
    public function period(Carbon $from, Carbon $to): array
    {
        $days = $this->days($from, $to);
        $users = [];
        foreach ($days as $r) {
            $u = $users[$r['user_id']] ?? ['name' => $r['name'], 'days' => 0, 'collected' => 0.0, 'daily' => 0.0, 'commission' => 0.0, 'total' => 0.0];
            $u['days']++;
            foreach (['collected', 'daily', 'commission', 'total'] as $k) $u[$k] = round($u[$k] + $r[$k], 2);
            $users[$r['user_id']] = $u;
        }
        return ['users' => $users, 'days' => $days];
    }

    /** For the waiter's POS screen: today's collections and the next target. */
    public function today(int $uid): array
    {
        $d = now()->startOfDay();
        $k = $d->toDateString();
        $c = $this->collections($d, $d);
        $r = $this->rules($d);
        $mine = round((float) ($c['waiter'][$k][$uid] ?? 0), 2);
        $cafe = round((float) ($c['cafe'][$k] ?? 0), 2);
        $ksh = fn ($n) => 'KSh ' . number_format((float) $n);

        if (! $r['active']) {
            $next = 'Pay targets start on ' . Carbon::parse((string) self::setting('pay_start'))->format('j M') . '.';
        } elseif ($mine + 0.004 < $r['threshold']) {
            $next = $ksh($r['threshold'] - $mine) . ' to your next target (' . $ksh($r['threshold']) . ' earns ' . $ksh($r['daily']) . ' daily pay)';
        } elseif ($mine <= $r['from']) {
            $next = 'Daily pay earned. ' . $ksh($r['from'] - $mine) . ' to your next target (above ' . $ksh($r['from']) . ' earns ' . rtrim(rtrim(number_format($r['pct'], 2), '0'), '.') . '% commission)';
        } else {
            $next = 'Daily pay earned. Commission so far ' . $ksh(($mine - $r['from']) * $r['pct'] / 100)
                . ($cafe + 0.004 < $r['gate'] ? ', paid if the caf' . "\u{00E9}" . ' collects ' . $ksh($r['gate']) . ' today (' . $ksh($r['gate'] - $cafe) . ' to go)' : '');
        }

        return ['collected' => $mine, 'cafe' => $cafe, 'next' => $next];
    }
}
