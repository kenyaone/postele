<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $month = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('month')) ? $request->query('month') : now()->format('Y-m');
        $from  = Carbon::createFromFormat('Y-m-d', $month . '-01')->startOfMonth();
        $to    = $from->copy()->endOfMonth();
        $cat   = $request->query('category');

        $query = Expense::with('recorder')->whereBetween('spent_on', [$from->toDateString(), $to->toDateString()]);
        if ($cat) $query->where('category', $cat);
        $expenses = $query->orderByDesc('spent_on')->orderByDesc('id')->get();

        $byCategory = Expense::whereBetween('spent_on', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('category, SUM(amount) as total, COUNT(*) as n')
            ->groupBy('category')->orderByDesc('total')->get();

        return view('manage.expenses.index', [
            'month'      => $month,
            'monthLabel' => $from->format('F Y'),
            'prevMonth'  => $from->copy()->subMonth()->format('Y-m'),
            'nextMonth'  => $from->copy()->addMonth()->format('Y-m'),
            'category'   => $cat,
            'expenses'   => $expenses,
            'byCategory' => $byCategory,
            'monthTotal' => (float) $byCategory->sum('total'),
            'categories' => $this->categories(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['recorded_by'] = $request->user()->id;
        $e = Expense::create($data);

        return redirect()->route('manage.expenses.index', ['month' => $e->spent_on->format('Y-m')])
            ->with('ok', 'Expense of KSh ' . number_format($e->amount, 2) . ' recorded.');
    }

    public function edit(Expense $expense)
    {
        return view('manage.expenses.edit', ['expense' => $expense, 'categories' => $this->categories()]);
    }

    public function update(Request $request, Expense $expense)
    {
        $expense->update($this->validated($request));

        return redirect()->route('manage.expenses.index', ['month' => $expense->spent_on->format('Y-m')])
            ->with('ok', 'Expense updated.');
    }

    public function destroy(Expense $expense)
    {
        $month = $expense->spent_on->format('Y-m');
        $expense->delete();

        return redirect()->route('manage.expenses.index', ['month' => $month])->with('ok', 'Expense deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'spent_on'    => 'required|date|before_or_equal:today',
            'category'    => 'required|string|max:40',
            'description' => 'nullable|string|max:160',
            'amount'      => 'required|numeric|min:0.01|max:10000000',
            'method'      => 'required|in:' . implode(',', array_keys(Expense::METHODS)),
            'reference'   => 'nullable|string|max:40',
        ], ['spent_on.before_or_equal' => 'The date cannot be in the future.']);
    }

    private function categories(): array
    {
        $used = Expense::distinct()->orderBy('category')->pluck('category')->all();
        return array_values(array_unique(array_merge(Expense::CATEGORIES, $used)));
    }
}
