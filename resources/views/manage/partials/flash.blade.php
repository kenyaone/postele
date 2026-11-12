@if (session('warn'))
    <div class="bg-amber-50 border border-amber-300 text-amber-900 rounded p-3 mb-4 text-sm">{{ session('warn') }}</div>
@endif
@if (session('ok'))
    <div class="bg-emerald-50 border border-emerald-300 text-emerald-800 rounded p-3 mb-4 text-sm">
        {{ session('ok') }}
    </div>
@endif
@if ($errors->any())
    <div class="bg-red-50 border border-red-300 text-red-800 rounded p-3 mb-4 text-sm">
        <ul class="list-disc list-inside">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
@endif
