@php $creditCustomers = \App\Models\Customer::where('is_platform', false)->orderBy('name')->get(['id', 'name', 'phone']); @endphp
<form method="POST" action="{{ route('pos.tickets.credit', $ticket) }}" class="space-y-3">
    @csrf
    <input type="hidden" name="credit_form" value="1">
    <p class="text-sm text-roast/70">The customer takes the food now and pays later. It shows under <b>Debts</b> until paid.</p>
    @if ($creditCustomers->isNotEmpty())
        <label class="block"><span class="text-xs font-bold uppercase tracking-wider text-roast/60">Customer who already has an account</span>
            <select name="customer_id" class="mt-1 w-full border rounded-xl px-3 py-3 bg-white" onchange="document.getElementById('credit-new').classList.toggle('hidden', this.value !== '')">
                <option value="">+ New customer</option>
                @foreach ($creditCustomers as $cc)
                    <option value="{{ $cc->id }}" @selected(old('customer_id') == $cc->id)>{{ $cc->name }}{{ $cc->phone ? ' - ' . $cc->phone : '' }}</option>
                @endforeach
            </select>
        </label>
    @endif
    <div id="credit-new" class="space-y-2 {{ old('customer_id') ? 'hidden' : '' }}">
        <input name="name" maxlength="80" value="{{ old('name') }}" placeholder="Customer name" class="w-full border rounded-xl px-3 py-3">
        <input name="phone" maxlength="20" value="{{ old('phone') }}" placeholder="Phone (optional)" data-keypad="phone" data-keypad-label="Customer phone" class="w-full border rounded-xl px-3 py-3">
    </div>
    <label class="block"><span class="text-xs font-bold uppercase tracking-wider text-roast/60">Promised to pay by (optional)</span>
        <input type="date" name="due_on" value="{{ old('due_on', now()->addDays(7)->toDateString()) }}" min="{{ now()->toDateString() }}" class="mt-1 w-full border rounded-xl px-3 py-3">
    </label>
    @foreach (['customer_id', 'name', 'phone', 'due_on', 'credit'] as $f)
        @error($f)<p class="text-sm font-semibold text-red-700">{{ $message }}</p>@enderror
    @endforeach
    <button class="w-full rounded-2xl bg-espresso hover:bg-roast text-crema font-extrabold py-4 text-lg">Put KSh {{ number_format($ticket->total) }} on account</button>
</form>
