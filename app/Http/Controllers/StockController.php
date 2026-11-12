<?php

namespace App\Http\Controllers;

use App\Models\StockCount;
use App\Models\StockItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Daily closing stock count + shopping list. Used by the Stock role and managers. */
class StockController extends Controller
{
    public function count(Request $request)
    {
        $date  = $this->dateFor($request);
        $items = StockItem::where('active', true)->orderBy('name')->get();

        $onDate = StockCount::with('counter')->where('counted_on', $date->toDateString())->get()->keyBy('stock_item_id');
        $latest = StockCount::where('counted_on', '<=', $date->toDateString())
            ->where('counted_on', '>=', $date->copy()->subDays(90)->toDateString())
            ->orderByDesc('counted_on')->get()->unique('stock_item_id')->keyBy('stock_item_id');

        $toBuy = $items->map(function ($i) use ($latest) {
            $c = $latest[$i->id] ?? null;
            if (! $c) return null;
            $qty = (float) $c->quantity;
            if ($qty > (float) $i->reorder_level) return null;
            return (object) [
                'item' => $i, 'qty' => $qty, 'counted_on' => $c->counted_on,
                'suggest' => $i->par_level !== null ? max(0, (float) $i->par_level - $qty) : null,
            ];
        })->filter()->values();

        return view('stock.count', [
            'date' => $date, 'items' => $items, 'onDate' => $onDate, 'latest' => $latest,
            'toBuy' => $toBuy, 'notCounted' => $items->filter(fn ($i) => ! isset($latest[$i->id]))->count(),
            'canPickDate' => $request->user()->role === 'manager',
        ]);
    }

    public function saveCounts(Request $request)
    {
        $date = $this->dateFor($request);
        $data = $request->validate([
            'counts'   => 'array',
            'counts.*' => 'nullable|numeric|min:0|max:1000000',
        ]);

        $active = StockItem::where('active', true)->pluck('id')->all();
        $saved = 0;
        foreach (($data['counts'] ?? []) as $itemId => $qty) {
            if ($qty === null || $qty === '' || ! in_array((int) $itemId, $active, true)) continue;
            StockCount::updateOrCreate(
                ['stock_item_id' => (int) $itemId, 'counted_on' => $date->toDateString()],
                ['quantity' => $qty, 'counted_by' => $request->user()->id]
            );
            $saved++;
        }

        return redirect()->route('stock.count', ['date' => $date->toDateString()])
            ->with('ok', "$saved count" . ($saved === 1 ? '' : 's') . ' saved for ' . $date->format('D j M') . '.');
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
