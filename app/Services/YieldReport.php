<?php

namespace App\Services;

use App\Models\StockCount;
use App\Models\TicketItem;
use App\Models\YieldNote;
use App\Models\YieldRule;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Raw material yield vs sales.
 * Used (actual)  = opening + bought - closing, from the daily stock sheet.
 * Expected       = portions sold (or KSh of sales) x raw_qty / output, from paid tickets.
 * Variance       = used - expected; flagged when outside the rule's tolerance.
 */
class YieldReport
{
    public function build(Carbon $from, Carbon $to): Collection
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('yield_rules')) {
            return collect();   // not migrated yet
        }
        $rules = YieldRule::with('stockItem', 'lines.item')->where('active', true)->get()
            ->sortBy(fn ($r) => $r->stockItem->name)->values();
        if ($rules->isEmpty()) {
            return collect();
        }

        $from = $from->copy()->startOfDay();
        $to = $to->copy()->startOfDay();
        $ids = $rules->pluck('stock_item_id')->all();

        $counts = StockCount::whereIn('stock_item_id', $ids)
            ->where('counted_on', '>=', $from->copy()->subDays(90)->toDateString())
            ->where('counted_on', '<=', $to->toDateString())
            ->orderBy('counted_on')->get()->groupBy('stock_item_id');

        $notes = YieldNote::with('explainer')->whereIn('stock_item_id', $ids)
            ->whereBetween('day', [$from->toDateString(), $to->toDateString()])->get()
            ->keyBy(fn ($n) => $n->stock_item_id . '|' . $n->day->toDateString());

        // Paid, non-voided sales per day per menu item.
        $byDay = TicketItem::query()
            ->join('tickets', 'tickets.id', '=', 'ticket_items.ticket_id')
            ->where('tickets.status', 'paid')
            ->whereNull('ticket_items.void_at')
            ->whereBetween('tickets.closed_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->selectRaw('DATE(tickets.closed_at) as d, ticket_items.item_id, SUM(ticket_items.qty) as qty, SUM(ticket_items.qty * ticket_items.unit_price) as value')
            ->groupByRaw('DATE(tickets.closed_at), ticket_items.item_id')
            ->get()->groupBy('d');

        $days = collect();
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $days->push($d->copy());
        }

        return $rules->map(function ($rule) use ($days, $counts, $notes, $byDay) {
            $list = $counts->get($rule->stock_item_id, collect());
            $units = $rule->lines->mapWithKeys(fn ($l) => [$l->item_id => (float) $l->units]);
            $rawPerOutput = (float) $rule->raw_qty / max(0.0001, (float) $rule->output);

            $rows = $days->map(function ($day) use ($rule, $list, $units, $rawPerOutput, $notes, $byDay) {
                $ds = $day->toDateString();
                $today = $list->first(fn ($c) => $c->counted_on->toDateString() === $ds);
                $prev = $list->filter(fn ($c) => $c->quantity !== null && $c->counted_on->toDateString() < $ds)->last();
                $opening = $prev ? (float) $prev->quantity : (($today && $today->opening !== null) ? (float) $today->opening : null);
                $used = ($today && $today->quantity !== null && $opening !== null)
                    ? $opening + (float) $today->received - (float) $today->quantity : null;

                $sales = $byDay->get($ds, collect());
                $out = $rule->basis === 'units'
                    ? $sales->sum(fn ($s) => isset($units[$s->item_id]) ? (float) $s->qty * $units[$s->item_id] : 0)
                    : $sales->sum(fn ($s) => ($rule->all_items || isset($units[$s->item_id])) ? (float) $s->value : 0);

                $expected = $out * $rawPerOutput;
                $variance = $used !== null ? $used - $expected : null;
                $pct = ($variance !== null && $expected > 0) ? $variance / $expected * 100 : null;
                $flag = $used !== null && (($pct !== null && abs($pct) > $rule->tolerance_pct) || ($expected == 0 && $used > 0));

                return (object) [
                    'day' => $day, 'out' => $out, 'expected' => $expected, 'used' => $used,
                    'variance' => $variance, 'pct' => $pct, 'flag' => $flag,
                    'note' => $notes->get($rule->stock_item_id . '|' . $ds),
                ];
            });

            $counted = $rows->filter(fn ($r) => $r->used !== null);
            $tUsed = (float) $counted->sum('used');
            $tExp = (float) $counted->sum('expected');
            $tOut = (float) $counted->sum('out');
            $total = (object) [
                'days' => $counted->count(), 'out' => $tOut, 'used' => $tUsed, 'expected' => $tExp,
                'variance' => $tUsed - $tExp,
                'pct' => $tExp > 0 ? ($tUsed - $tExp) / $tExp * 100 : null,
                'perRaw' => $tUsed > 0 ? $tOut / $tUsed : null,                                    // portions or KSh per unit actually achieved
                'targetPerRaw' => (float) $rule->output / max(0.0001, (float) $rule->raw_qty),
            ];
            $total->flag = $total->pct !== null && abs($total->pct) > $rule->tolerance_pct;

            return (object) ['rule' => $rule, 'rows' => $rows, 'total' => $total];
        });
    }

    /** Flagged days with no explanation yet, newest first (dashboard alerts). */
    public function openAlerts(int $days = 7): Collection
    {
        $to = now()->startOfDay();

        return $this->build($to->copy()->subDays($days - 1), $to)
            ->flatMap(fn ($r) => $r->rows->filter(fn ($row) => $row->flag && ! $row->note)
                ->map(fn ($row) => (object) ['rule' => $r->rule, 'row' => $row]))
            ->sortByDesc(fn ($a) => $a->row->day->timestamp)->values();
    }
}
