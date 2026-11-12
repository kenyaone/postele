<?php

namespace App\Http\Controllers;

use App\Models\StockCount;
use App\Models\StockItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Daily stock sheet (opening + bought - closing = used), shopping list and stock items. Used by the Stock role and managers. */
class StockController extends Controller
{
    /** Daily stock sheet: opening (previous closing) + bought today - closing count = used. */
    public function count(Request $request)
    {
        $date = $this->dateFor($request);
        $d = $date->toDateString();
        $from = $date->copy()->subDays(90)->toDateString();
        $items = StockItem::where('active', true)->orderBy('name')->get();

        $today = StockCount::with('counter')->where('counted_on', $d)->get()->keyBy('stock_item_id');
        // Latest closing count before this day, per item (ordered ascending so the latest wins in keyBy).
        $prev = StockCount::whereNotNull('quantity')->where('counted_on', '<', $d)->where('counted_on', '>=', $from)
            ->orderBy('counted_on')->get()->keyBy('stock_item_id');

        $rows = $items->map(function ($i) use ($today, $prev, $date) {
            $t = $today->get($i->id);
            $p = $prev->get($i->id);
            $opening = $p ? (float) $p->quantity : ($t && $t->opening !== null ? (float) $t->opening : null);
            $received = $t ? (float) $t->received : 0.0;
            $closing = ($t && $t->quantity !== null) ? (float) $t->quantity : null;
            return (object) [
                'item'        => $i,
                'opening'     => $opening,
                'openingFrom' => $p ? $p->counted_on : null,
                'openingOld'  => $p && $p->counted_on->lt($date->copy()->subDay()),   // last count is older than yesterday
                'firstDay'    => ! $p,
                'received'    => $received,
                'closing'     => $closing,
                'used'        => ($opening !== null && $closing !== null) ? $opening + $received - $closing : null,
                'by'          => $t?->counter?->name,
            ];
        });

        // Shopping list: latest closing count at or below the reorder level.
        $latest = StockCount::whereNotNull('quantity')->where('counted_on', '<=', $d)->where('counted_on', '>=', $from)
            ->orderBy('counted_on')->get()->keyBy('stock_item_id');
        $toBuy = $items->map(function ($i) use ($latest) {
            $c = $latest->get($i->id);
            if (! $c || $i->reorder_level === null) return null;
            $qty = (float) $c->quantity;
            if ($qty > (float) $i->reorder_level) return null;
            return (object) [
                'item' => $i, 'qty' => $qty, 'counted_on' => $c->counted_on,
                'suggest' => $i->par_level !== null ? max(0, (float) $i->par_level - $qty) : null,
            ];
        })->filter()->values();

        return view('stock.count', [
            'date'        => $date,
            'rows'        => $rows,
            'toBuy'       => $toBuy,
            'canPickDate' => $request->user()->role === 'manager',
            'closedCount' => $rows->whereNotNull('closing')->count(),
        ]);
    }

    public function saveCounts(Request $request)
    {
        $date = $this->dateFor($request);
        $d = $date->toDateString();
        $num = ['nullable', 'numeric', 'min:0', 'max:999999'];
        $data = $request->validate([
            'received' => ['array'], 'received.*' => $num,
            'closing'  => ['array'], 'closing.*'  => $num,
            'opening'  => ['array'], 'opening.*'  => $num,
        ]);

        $hasPrev = StockCount::whereNotNull('quantity')->where('counted_on', '<', $d)->pluck('stock_item_id')->flip();
        $saved = 0;
        foreach (StockItem::where('active', true)->pluck('id') as $id) {
            $vals = [];
            foreach (['received' => 'received', 'closing' => 'quantity', 'opening' => 'opening'] as $field => $col) {
                if (! array_key_exists($id, $data[$field] ?? [])) continue;   // not on the form
                $v = $data[$field][$id];
                $vals[$col] = ($v === null || $v === '') ? null : (float) $v;
            }
            if (isset($hasPrev[$id])) unset($vals['opening']);              // opening comes from the previous closing count
            if (array_key_exists('received', $vals) && $vals['received'] === null) $vals['received'] = 0;

            $row = StockCount::firstOrNew(['stock_item_id' => $id, 'counted_on' => $d]);
            if (! $row->exists && ($vals['quantity'] ?? null) === null && (float) ($vals['received'] ?? 0) == 0 && ($vals['opening'] ?? null) === null) continue;
            $row->fill($vals);
            if ($row->isDirty()) {
                $row->counted_by = $request->user()->id;
                $row->save();
                $saved++;
            }
        }

        return redirect()->route('stock.count', ['date' => $d])
            ->with('ok', "Stock sheet saved for " . $date->format('D j M') . " ($saved item" . ($saved === 1 ? '' : 's') . " updated).");
    }

    public function items()
    {
        return view('stock.items', ['items' => StockItem::orderByDesc('active')->orderBy('name')->get()]);
    }

    public function storeItem(Request $request)
    {
        StockItem::create($this->itemData($request));
        return redirect()->route('stock.items')->with('ok', 'Stock item added.');
    }

    public function updateItem(Request $request, StockItem $stockItem)
    {
        $stockItem->update($this->itemData($request, $stockItem));
        return redirect()->route('stock.items')->with('ok', "\u{201C}{$stockItem->name}\u{201D} saved.");
    }

    /** Printable shopping list for the market trip (80mm, auto-prints). */
    public function printShopping(Request $request)
    {
        $date = $this->dateFor($request);
        $d = $date->toDateString();
        $items = StockItem::where('active', true)->orderBy('name')->get();
        $latest = StockCount::whereNotNull('quantity')->where('counted_on', '<=', $d)
            ->where('counted_on', '>=', $date->copy()->subDays(90)->toDateString())
            ->orderBy('counted_on')->get()->keyBy('stock_item_id');
        $toBuy = $items->map(function ($i) use ($latest) {
            $c = $latest->get($i->id);
            if (! $c || $i->reorder_level === null) return null;
            $qty = (float) $c->quantity;
            if ($qty > (float) $i->reorder_level) return null;
            return (object) [
                'item' => $i, 'qty' => $qty,
                'suggest' => $i->par_level !== null ? max(0, (float) $i->par_level - $qty) : null,
            ];
        })->filter()->values();

        return view('stock.shopping-print', ['toBuy' => $toBuy, 'by' => $request->user()->name]);
    }

    private function itemData(Request $request, ?StockItem $item = null): array
    {
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:80', Rule::unique('stock_items', 'name')->ignore($item?->id)],
            'unit'          => 'required|string|max:20',
            'reorder_level' => 'required|numeric|min:0|max:1000000',
            'par_level'     => 'nullable|numeric|min:0|max:1000000|gte:reorder_level',
            'active'        => 'nullable|boolean',
        ], ['par_level.gte' => 'The "buy up to" level must be at least the reorder level.']);
        $data['active'] = $item ? (bool) ($data['active'] ?? false) : true;
        return $data;
    }

    /** Managers may look at / correct past days; stock staff always count for today. */
    private function dateFor(Request $request): Carbon
    {
        $today = now()->startOfDay();
        if ($request->user()->role !== 'manager' || ! $request->filled('date')) return $today;
        try {
            $d = Carbon::createFromFormat('Y-m-d', (string) $request->input('date'))->startOfDay();
        } catch (\Throwable $e) {
            return $today;
        }
        return $d->gt($today) ? $today : $d;
    }
}
