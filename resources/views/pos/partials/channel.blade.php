@php
    $chan = $ticket ? ($ticket->channel ?? 'house') : session('pos_channel', 'house');
    $chanLocked = $ticket && ($ticket->status !== 'open' || $ticket->items->isNotEmpty());
@endphp
<form method="POST" action="{{ route('pos.channel') }}" class="flex gap-2 mb-3">
    @csrf
    @if ($ticket)<input type="hidden" name="ticket_id" value="{{ $ticket->id }}">@endif
    @foreach (['house' => '&#127869; Dine-in / takeaway', 'glovo' => '&#128757; Glovo'] as $k => $label)
        <button name="channel" value="{{ $k }}" @disabled($chanLocked && $chan !== $k)
            class="flex-1 rounded-2xl py-3 text-sm font-extrabold ring-2 transition {{ $chan === $k ? ($k === 'glovo' ? 'bg-orange-500 text-white ring-orange-500 shadow' : 'bg-espresso text-crema ring-espresso shadow') : 'bg-white ring-roast/15 hover:ring-saffron disabled:opacity-30' }}">{!! $label !!}</button>
    @endforeach
</form>
@error('channel')<p class="-mt-1 mb-3 text-sm font-semibold text-red-700">{{ $message }}</p>@enderror
