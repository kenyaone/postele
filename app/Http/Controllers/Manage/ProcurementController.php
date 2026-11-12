<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Purchase;
use App\Models\PurchasePayment;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProcurementController extends Controller
{
    public function index(Request $request)
    {
        $month = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('month')) ? $request->query('month') : now()->format('Y-m');
        $from  = Carbon::createFromFormat('Y-m-d', $month . '-01')->startOfMonth();
        $to    = $from->copy()->endOfMonth();

        $purchases = Purchase::with('supplier')->withSum('payments as paid', 'amount')
            ->whereBetween('purchased_on', [$from->toDateString(), $to->toDateString()])
            ->orderByDesc('purchased_on')->orderByDesc('id')->get();

        $unpaid = Purchase::with('supplier')->withSum('payments as paid', 'amount')
            ->orderBy('due_on')->orderBy('purchased_on')->get()
            ->filter(fn ($p) => $p->status() !== 'paid')->values();

        return view('manage.procurement.index', [
            'month'      => $month,
            'monthLabel' => $from->format('F Y'),
            'prevMonth'  => $from->copy()->subMonth()->format('Y-m'),
            'nextMonth'  => $from->copy()->addMonth()->format('Y-m'),
            'purchases'  => $purchases,
            'monthTotal' => (float) $purchases->sum('total'),
            'unpaid'     => $unpaid,
            'owedTotal'  => $unpaid->sum(fn ($p) => $p->balance()),
            'suppliers'  => $this->supplierBalances(),
        ]);
    }

    public function storePurchase(Request $request)
    {
        $data = $request->validate($this->purchaseRules() + [
            'paid_now' => 'required|in:full,none',
            'method'   => 'required|in:' . implode(',', array_keys(Expense::METHODS)),
        ]);

        $purchase = DB::transaction(function () use ($data, $request) {
            $supplier = Supplier::firstOrCreate(['name' => trim($data['supplier'])]);
            $p = Purchase::create([
                'supplier_id'  => $supplier->id,
                'purchased_on' => $data['purchased_on'],
                'reference'    => $data['reference'] ?? null,
                'description'  => $data['description'],
                'total'        => $data['total'],
                'due_on'       => $data['paid_now'] === 'full' ? null : ($data['due_on'] ?? null),
                'recorded_by'  => $request->user()->id,
            ]);
            if ($data['paid_now'] === 'full') {
                $p->payments()->create([
                    'paid_on' => $data['purchased_on'], 'amount' => $data['total'],
                    'method' => $data['method'], 'recorded_by' => $request->user()->id,
                ]);
            }
            return $p;
        });

        return redirect()->route('manage.procurement', ['month' => $purchase->purchased_on->format('Y-m')])
            ->with('ok', 'Purchase of KSh ' . number_format($purchase->total, 2) . ' from ' . $purchase->supplier->name . ' recorded.');
    }

    public function showPurchase(Purchase $purchase)
    {
        $purchase->load(['supplier', 'recorder', 'payments.recorder']);
        return view('manage.procurement.purchase', [
            'purchase'  => $purchase,
            'suppliers' => Supplier::orderBy('name')->pluck('name'),
        ]);
    }

    public function updatePurchase(Request $request, Purchase $purchase)
    {
        $data = $request->validate($this->purchaseRules());
        $paid = $purchase->paidAmount();
        if ((float) $data['total'] + 0.004 < $paid) {
            return back()->withInput()->withErrors(['total' => 'Total cannot be less than what is already paid (KSh ' . number_format($paid, 2) . ').']);
        }
        $supplier = Supplier::firstOrCreate(['name' => trim($data['supplier'])]);
        $purchase->update([
            'supplier_id' => $supplier->id, 'purchased_on' => $data['purchased_on'], 'reference' => $data['reference'] ?? null,
            'description' => $data['description'], 'total' => $data['total'], 'due_on' => $data['due_on'] ?? null,
        ]);
        return redirect()->route('manage.procurement.purchase', $purchase)->with('ok', 'Purchase updated.');
    }

    public function destroyPurchase(Purchase $purchase)
    {
        $month = $purchase->purchased_on->format('Y-m');
        $purchase->delete();
        return redirect()->route('manage.procurement', ['month' => $month])->with('ok', 'Purchase and its payments deleted.');
    }

    public function storePayment(Request $request, Purchase $purchase)
    {
        $balance = $purchase->balance();
        $data = $request->validate([
            'paid_on'   => 'required|date|before_or_equal:today',
            'amount'    => 'required|numeric|min:0.01|max:' . max(0.01, $balance),
            'method'    => 'required|in:' . implode(',', array_keys(Expense::METHODS)),
            'reference' => 'nullable|string|max:40',
        ], ['amount.max' => 'That is more than the balance of KSh ' . number_format($balance, 2) . '.']);

        $purchase->payments()->create($data + ['recorded_by' => $request->user()->id]);
        return redirect()->route('manage.procurement.purchase', $purchase)->with('ok', 'Payment of KSh ' . number_format($data['amount'], 2) . ' recorded.');
    }

    public function destroyPayment(PurchasePayment $payment)
    {
        $purchase = $payment->purchase;
        $payment->delete();
        return redirect()->route('manage.procurement.purchase', $purchase)->with('ok', 'Payment removed.');
    }

    public function storeSupplier(Request $request)
    {
        $data = $request->validate([
            'name'  => 'required|string|max:80|unique:suppliers,name',
            'phone' => 'nullable|string|max:30',
            'notes' => 'nullable|string|max:200',
        ]);
        Supplier::create($data);
        return redirect()->route('manage.procurement')->with('ok', 'Supplier added.');
    }

    public function showSupplier(Supplier $supplier)
    {
        $purchases = $supplier->purchases()->withSum('payments as paid', 'amount')->orderByDesc('purchased_on')->get();
        return view('manage.procurement.supplier', [
            'supplier'  => $supplier,
            'purchases' => $purchases,
            'bought'    => (float) $purchases->sum('total'),
            'owed'      => $purchases->sum(fn ($p) => max(0, $p->balance())),
        ]);
    }

    public function updateSupplier(Request $request, Supplier $supplier)
    {
        $data = $request->validate([
            'name'   => ['required', 'string', 'max:80', Rule::unique('suppliers', 'name')->ignore($supplier->id)],
            'phone'  => 'nullable|string|max:30',
            'notes'  => 'nullable|string|max:200',
            'contact'  => 'nullable|string|max:60',
            'category' => 'nullable|string|max:40',
            'terms'    => 'nullable|string|max:30',
            'active' => 'nullable|boolean',
        ]);
        $supplier->update($data + ['active' => (bool) ($data['active'] ?? false)]);
        return redirect()->route('manage.procurement.supplier', $supplier)->with('ok', 'Supplier saved.');
    }

    private function purchaseRules(): array
    {
        return [
            'supplier'     => 'required|string|max:80',
            'purchased_on' => 'required|date|before_or_equal:today',
            'description'  => 'required|string|max:200',
            'total'        => 'required|numeric|min:0.01|max:100000000',
            'reference'    => 'nullable|string|max:40',
            'due_on'       => 'nullable|date|after_or_equal:purchased_on',
        ];
    }

    private function supplierBalances()
    {
        $bought = Purchase::selectRaw('supplier_id, SUM(total) as t')->groupBy('supplier_id')->pluck('t', 'supplier_id');
        $paid = PurchasePayment::join('purchases', 'purchase_payments.purchase_id', '=', 'purchases.id')
            ->selectRaw('purchases.supplier_id, SUM(purchase_payments.amount) as p')->groupBy('purchases.supplier_id')->pluck('p', 'supplier_id');
        return Supplier::orderByDesc('active')->orderBy('name')->get()->map(function ($s) use ($bought, $paid) {
            $s->bought = (float) ($bought[$s->id] ?? 0);
            $s->owed   = round($s->bought - (float) ($paid[$s->id] ?? 0), 2);
            return $s;
        });
    }
}
