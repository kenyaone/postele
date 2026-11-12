<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Item;
use App\Models\StockItem;
use App\Models\YieldNote;
use App\Models\YieldRule;
use App\Services\YieldReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Raw material yield vs sales: report + kitchen explanations (stock & managers), rules (managers). */
class YieldController extends Controller
{
    public function index(Request $request, YieldReport $report)
    {
        $days = in_array((int) $request->query('days'), [7, 14, 30], true) ? (int) $request->query('days') : 7;
        $to = now()->startOfDay();
        $from = $to->copy()->subDays($days - 1);

        return view('stock.yield', [
            'report'    => $report->build($from, $to),
            'days'      => $days,
            'from'      => $from,
            'to'        => $to,
            'isManager' => $request->user()->role === 'manager',
        ]);
    }

    public function storeNote(Request $request)
    {
        $data = $request->validate([
            'stock_item_id' => ['required', 'integer', 'exists:stock_items,id'],
            'day'           => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'reason'        => ['required', 'string', 'max:500'],
        ]);
        YieldNote::updateOrCreate(
            ['stock_item_id' => $data['stock_item_id'], 'day' => $data['day']],
            ['reason' => $data['reason'], 'explained_by' => $request->user()->id]
        );

        return back()->with('ok', 'Explanation saved.');
    }

    public function create(Request $request)
    {
        $this->managerOnly($request);
        return $this->form(new YieldRule(['basis' => 'units', 'tolerance_pct' => 10, 'active' => true]));
    }

    public function edit(Request $request, YieldRule $rule)
    {
        $this->managerOnly($request);
        return $this->form($rule->load('lines'));
    }

    public function store(Request $request)
    {
        $this->managerOnly($request);
        $this->save($request, new YieldRule);
        return redirect()->route('stock.yield')->with('ok', 'Yield rule saved.');
    }

    public function update(Request $request, YieldRule $rule)
    {
        $this->managerOnly($request);
        $this->save($request, $rule);
        return redirect()->route('stock.yield')->with('ok', 'Yield rule updated.');
    }

    private function form(YieldRule $rule)
    {
        return view('stock.yield-rule', [
            'rule'       => $rule,
            'stockItems' => StockItem::where('active', true)->orderBy('name')->get(),
            'categories' => Category::with(['items' => fn ($q) => $q->where('active', true)->orderBy('name')])->orderBy('name')->get(),
            'units'      => $rule->exists ? $rule->lines->mapWithKeys(fn ($l) => [$l->item_id => (float) $l->units]) : collect(),
        ]);
    }

    private function save(Request $request, YieldRule $rule): void
    {
        $data = $request->validate([
            'stock_item_id' => ['required', 'integer', 'exists:stock_items,id', Rule::unique('yield_rules', 'stock_item_id')->ignore($rule->id)],
            'basis'         => ['required', Rule::in(['units', 'sales'])],
            'raw_qty'       => ['required', 'numeric', 'gt:0', 'max:99999'],
            'output'        => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'tolerance_pct' => ['required', 'integer', 'min:1', 'max:100'],
            'units'         => ['array'],
            'units.*'       => ['nullable', 'numeric', 'gt:0', 'max:1000'],
        ], ['stock_item_id.unique' => 'That raw material already has a yield rule. Edit it instead.']);

        $all = $data['basis'] === 'sales' && $request->boolean('all_items');
        $lines = collect($data['units'] ?? [])->filter(fn ($u) => $u !== null && $u !== '');
        $valid = Item::whereIn('id', $lines->keys())->pluck('id')->flip();
        $lines = $lines->filter(fn ($u, $id) => isset($valid[$id]));
        if ($lines->isEmpty() && ! $all) {
            throw ValidationException::withMessages(['units' => 'Choose the menu items made from this raw material.']);
        }

        DB::transaction(function () use ($rule, $data, $lines, $all, $request) {
            $rule->fill([
                'stock_item_id' => $data['stock_item_id'],
                'basis'         => $data['basis'],
                'raw_qty'       => $data['raw_qty'],
                'output'        => $data['output'],
                'tolerance_pct' => $data['tolerance_pct'],
                'all_items'     => $all,
                'active'        => $request->boolean('active'),
            ])->save();
            $rule->lines()->delete();
            foreach ($lines as $itemId => $u) {
                $rule->lines()->create(['item_id' => $itemId, 'units' => $u]);
            }
        });
    }

    private function managerOnly(Request $request): void
    {
        abort_unless($request->user()->role === 'manager', 403, 'Only a manager can change yield rules.');
    }
}
