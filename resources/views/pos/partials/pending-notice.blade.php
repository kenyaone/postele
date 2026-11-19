@php $pending = auth()->check() ? \App\Http\Controllers\Pos\ClearController::pendingFor(auth()->user()) : ['count' => 0]; @endphp
@if ($pending['count'] > 0)
    <a href="{{ route('pos.clear') }}" class="block {{ $class ?? '' }} rounded-2xl bg-red-600 hover:bg-red-700 text-white px-4 py-3 shadow ring-1 ring-red-700">
        <span class="font-extrabold">&#9888; You have {{ $pending['count'] }} unpaid {{ $pending['count'] === 1 ? 'bill' : 'bills' }} (KSh {{ number_format($pending['total']) }}).</span>
        <span class="block text-sm text-red-50">Tap here and type the customer's M-Pesa code to clear {{ $pending['count'] === 1 ? 'it' : 'them' }}. You are paid only when all your bills are cleared.</span>
    </a>
@endif
