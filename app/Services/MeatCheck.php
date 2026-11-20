<?php

namespace App\Services;

use App\Models\StockCount;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Daily meat check: kg used (from the stock count: opening + received - closing) against plates sold on bills.
 * A day below MIN_PER_KG plates per kg, or with no closing count, is flagged.
 */
class MeatCheck
{
    public const MIN_PER_KG = 6;

    /** stock_item_id, menu-name patterns, target plates per kg, buying price per kg. */
    public const MEATS = [
        'Beef' => ['stock' => 12, 'match' => ['beef', 'nyama'], 'target' => 8, 'price_kg' => 550],
        'Pork' => ['stock' => 13, 'match' => ['pork'],          'target' => 7, 'price_kg' => 380],
    ];

    public function day(Carbon $day): array
    {
        $out = [];
        foreach (self::MEATS as $name => $m) {
            $c = StockCount::where('stock_item_id', $m['stock'])->whereDate('counted_on', $day)->first();
            $prev = StockCount::where('stock_item_id', $m['stock'])->whereNotNull('quantity')
                ->whereDate('counted_on', '<', $day)->orderByDesc('counted_on')->first();
            $opening = $c && $c->opening !== null ? (float) $c->opening : ($prev ? (float) $prev->quantity : null);
            $received = $c ? (float) $c->received : 0.0;
            $closing = $c && $c->quantity !== null ? (float) $c->quantity : null;
            $used = ($opening !== null && $closing !== null) ? round($opening + $received - $closing, 2) : null;
            $plates = (float) DB::table('ticket_items')
                ->join('tickets', 'tickets.id', '=', 'ticket_items.ticket_id')
                ->join('items', 'items.id', '=', 'ticket_items.item_id')
                ->where('tickets.status', '!=', 'void')->whereNull('ticket_items.void_at')
                ->whereBetween('tickets.opened_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])
                ->where(function ($q) use ($m) { foreach ($m['match'] as $w) { $q->orWhere('items.name', 'like', "%{$w}%"); } })
                ->sum('ticket_items.qty');
            $perKg = ($used && $used > 0) ? round($plates / $used, 1) : null;
            $missing = $used !== null ? max(0, round($used * $m['target'] - $plates)) : null;
            $out[$name] = (object) [
                'opening' => $opening, 'received' => $received, 'closing' => $closing, 'used' => $used,
                'plates' => $plates, 'perKg' => $perKg, 'target' => $m['target'], 'missing' => $missing,
                'missingCost' => $missing !== null ? round($missing / $m['target'] * $m['price_kg']) : null,
                'counted' => $closing !== null,
                'flag' => $closing === null || ($perKg !== null && $perKg < self::MIN_PER_KG),
            ];
        }
        return $out;
    }

    /** day string => day() result, oldest first. */
    public function range(Carbon $from, Carbon $to): array
    {
        $rows = [];
        for ($d = $from->copy()->startOfDay(); $d->lte($to); $d->addDay()) { $rows[$d->toDateString()] = $this->day($d->copy()); }
        return $rows;
    }
}
