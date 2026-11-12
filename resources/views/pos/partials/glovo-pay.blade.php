<div class="rounded-2xl bg-orange-50 ring-2 ring-orange-300 p-4 text-center space-y-3">
    <div class="text-xs font-bold uppercase tracking-wider text-orange-700">&#128757; Glovo order</div>
    <p class="text-sm text-orange-900">Glovo pays us later, so this goes on Glovo's account. No cash or M-Pesa needed.</p>
    <form method="POST" action="{{ route('pos.tickets.credit', $ticket) }}">
        @csrf
        <button class="w-full rounded-2xl bg-orange-500 hover:bg-orange-600 text-white font-extrabold py-4 text-lg shadow">Put KSh {{ number_format($ticket->total) }} on Glovo's account</button>
    </form>
    @error('credit')<p class="text-sm font-semibold text-red-700">{{ $message }}</p>@enderror
</div>
