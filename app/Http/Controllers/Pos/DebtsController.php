<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\CreditPayment;
use App\Models\CreditSale;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Debts / credit sales: who owes what, their orders, and repayments (cashiers and managers record them). */
class DebtsController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $all = $request->boolean('all');
        $customers = Customer::withTotals()->withMax('creditSales as last_sale_at', 'created_at')
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('name', 'like', '%' . $q . '%')->orWhere('phone', 'like', '%' . $q . '%')))
            ->orderByDesc('is_platform')->orderBy('name')->get();
        if (! $all) {
            $customers = $customers->filter(fn ($c) => $c->balance > 0.004 || $c->is_platform)->values();
        }
        $overdue = CreditSale::whereNotNull('due_on')->where('due_on', '<', now()->toDateString())->pluck('customer_id')->unique()->flip();

        return view('pos.debts.index', [
            'customers' => $customers, 'q' => $q, 'all' => $all, 'overdue' => $overdue,
            'totalOwed' => $customers->sum(fn ($c) => max(0, $c->balance)),
        ]);
    }

    public function show(Request $request, Customer $customer)
    {
        $customer->loadSum('creditSales as owed_total', 'amount')->loadSum('payments as paid_total', 'amount')->loadSum('payments as fees_total', 'fees');

        return view('pos.debts.show', [
            'customer'  => $customer,
            'sales'     => $customer->creditSales()->with(['ticket.items.item', 'ticket.opener', 'recorder'])->latest()->limit(300)->get(),
            'payments'  => $customer->payments()->with('recorder')->latest('received_at')->limit(300)->get(),
            'canRecord' => in_array($request->user()->role, ['manager', 'cashier'], true),
        ]);
    }

    public function storePayment(Request $request, Customer $customer)
    {
        abort_unless(in_array($request->user()->role, ['manager', 'cashier'], true), 403, 'Only a cashier or manager can record payments.');
        $data = $request->validate([
            'amount'    => ['required', 'numeric', 'min:0', 'max:9999999'],
            'fees'      => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'method'    => ['required', 'in:cash,mpesa,bank,other'],
            'reference' => ['nullable', 'string', 'max:60'],
            'note'      => ['nullable', 'string', 'max:255'],
        ]);
        if ((float) $data['amount'] + (float) ($data['fees'] ?? 0) <= 0) {
            throw ValidationException::withMessages(['amount' => 'Enter the amount received.']);
        }
        CreditPayment::create([
            'customer_id' => $customer->id,
            'amount'      => $data['amount'],
            'fees'        => $data['fees'] ?? 0,
            'method'      => $data['method'],
            'reference'   => ! empty($data['reference']) ? strtoupper(trim($data['reference'])) : null,
            'note'        => $data['note'] ?? null,
            'received_at' => now(),
            'recorded_by' => $request->user()->id,
        ]);

        return back()->with('ok', 'Payment of KSh ' . number_format((float) $data['amount']) . ' recorded for ' . $customer->name . '.');
    }
}
