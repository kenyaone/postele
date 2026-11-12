@extends('layouts.manage')
@section('title', 'Menu')

@section('content')
<div class="">

        {{-- CATEGORIES --}}
        {{-- Row inputs join their row's update form via the HTML5 form="" attribute; forms cannot wrap table cells. --}}
        <section class="bg-white rounded-xl shadow p-6 mb-8">
            <h2 class="text-lg font-semibold mb-4">Categories</h2>

            <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-gray-500 border-b">
                    <tr>
                        <th class="py-2 pr-2">Name</th>
                        <th class="py-2 px-2 w-24">Sort</th>
                        <th class="py-2 px-2 w-20">Active</th>
                        <th class="py-2 px-2 w-24">Items</th>
                        <th class="py-2 pl-2 w-40 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($categories as $c)
                    @php $hasItems = $c->items()->exists(); @endphp
                    <tr class="border-b last:border-0 align-middle">
                        <td class="py-2 pr-2">
                            <input form="cat-form-{{ $c->id }}" name="name" value="{{ $c->name }}" required
                                   class="w-full border rounded px-2 py-1">
                        </td>
                        <td class="py-2 px-2">
                            <input form="cat-form-{{ $c->id }}" type="number" name="sort_order" value="{{ $c->sort_order }}" min="0" max="9999"
                                   class="w-full border rounded px-2 py-1">
                        </td>
                        <td class="py-2 px-2">
                            <input form="cat-form-{{ $c->id }}" type="hidden" name="active" value="0">
                            <input form="cat-form-{{ $c->id }}" type="checkbox" name="active" value="1" {{ $c->active ? 'checked' : '' }}>
                        </td>
                        <td class="py-2 px-2 text-gray-500">{{ $c->items()->count() }}</td>
                        <td class="py-2 pl-2 text-right whitespace-nowrap">
                            <form method="POST" action="{{ route('manage.categories.update', $c) }}" id="cat-form-{{ $c->id }}" class="inline">
                                @csrf @method('PATCH')
                                <button class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs rounded px-3 py-1">Save</button>
                            </form>
                            <form method="POST" action="{{ route('manage.categories.destroy', $c) }}" class="inline"
                                  onsubmit="return confirm('Delete category &ldquo;{{ $c->name }}&rdquo;?');">
                                @csrf @method('DELETE')
                                <button class="bg-red-600 hover:bg-red-700 text-white text-xs rounded px-3 py-1 ml-1 {{ $hasItems ? 'opacity-40 cursor-not-allowed' : '' }}"
                                        {{ $hasItems ? 'disabled title=Has-items' : '' }}>
                                    Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            </div>

            {{-- New category --}}
            <form method="POST" action="{{ route('manage.categories.store') }}" class="mt-4 flex gap-2 items-end">
                @csrf
                <div>
                    <label class="block text-xs text-gray-500">New category</label>
                    <input name="name" required placeholder="e.g. Pastries" class="border rounded px-2 py-1">
                </div>
                <div>
                    <label class="block text-xs text-gray-500">Sort</label>
                    <input type="number" name="sort_order" min="0" max="9999" placeholder="auto" class="border rounded px-2 py-1 w-24">
                </div>
                <button class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm rounded px-4 py-1">Add</button>
            </form>
        </section>

        {{-- ITEMS --}}
        <section class="bg-white rounded-xl shadow p-6">
            <h2 class="text-lg font-semibold mb-4">Items</h2>

            @foreach($items as $cat)
                <div class="mb-6">
                    <h3 class="font-semibold text-emerald-800 border-b pb-1 mb-2">
                        {{ $cat->name }}
                        <span class="text-gray-400 text-sm font-normal">({{ $cat->items->count() }})</span>
                    </h3>

                    @if($cat->items->isEmpty())
                        <p class="text-sm text-gray-400 italic mb-2">No items yet.</p>
                    @else
                    <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-left text-gray-500 border-b">
                            <tr>
                                <th class="py-2 pr-2">Name</th>
                                <th class="py-2 px-2 w-40">Category</th>
                                <th class="py-2 px-2 w-28">Price (KES)</th>
                                <th class="py-2 px-2 w-28">Station</th>
                                <th class="py-2 px-2 w-20">Active</th>
                                <th class="py-2 pl-2 w-40 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($cat->items as $it)
                            <tr class="border-b last:border-0 align-middle">
                                <td class="py-2 pr-2">
                                    <input form="item-form-{{ $it->id }}" name="name" value="{{ $it->name }}" required
                                           class="w-full border rounded px-2 py-1">
                                </td>
                                <td class="py-2 px-2">
                                    <select form="item-form-{{ $it->id }}" name="category_id" class="w-full border rounded px-2 py-1">
                                        @foreach($categories as $c)
                                            <option value="{{ $c->id }}" {{ $c->id == $it->category_id ? 'selected' : '' }}>
                                                {{ $c->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="py-2 px-2">
                                    <input form="item-form-{{ $it->id }}" type="number" name="price" step="0.01" min="0" value="{{ $it->price }}" required
                                           class="w-full border rounded px-2 py-1 text-right">
                                </td>
                                <td class="py-2 px-2">
                                    <select form="item-form-{{ $it->id }}" name="station" class="w-full border rounded px-2 py-1">
                                        <option value="kitchen" {{ $it->station=='kitchen'?'selected':'' }}>kitchen</option>
                                        <option value="cashier" {{ $it->station=='cashier'?'selected':'' }}>cashier</option>
                                    </select>
                                </td>
                                <td class="py-2 px-2">
                                    <input form="item-form-{{ $it->id }}" type="hidden" name="active" value="0">
                                    <input form="item-form-{{ $it->id }}" type="checkbox" name="active" value="1" {{ $it->active ? 'checked' : '' }}>
                                </td>
                                <td class="py-2 pl-2 text-right whitespace-nowrap">
                                    <form method="POST" action="{{ route('manage.items.update', $it) }}" id="item-form-{{ $it->id }}" class="inline">
                                        @csrf @method('PATCH')
                                        <button class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs rounded px-3 py-1">Save</button>
                                    </form>
                                    <form method="POST" action="{{ route('manage.items.destroy', $it) }}" class="inline"
                                          onsubmit="return confirm('Delete item &ldquo;{{ $it->name }}&rdquo;?');">
                                        @csrf @method('DELETE')
                                        <button class="bg-red-600 hover:bg-red-700 text-white text-xs rounded px-3 py-1 ml-1">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    </div>
                    @endif
                </div>
            @endforeach

            {{-- New item --}}
            <div class="mt-6 border-t pt-4">
                <h3 class="font-semibold mb-2">Add item</h3>
                <form method="POST" action="{{ route('manage.items.store') }}" class="flex flex-wrap gap-2 items-end">
                    @csrf
                    <div>
                        <label class="block text-xs text-gray-500">Name</label>
                        <input name="name" required class="border rounded px-2 py-1">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">Category</label>
                        <select name="category_id" required class="border rounded px-2 py-1">
                            @foreach($categories as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">Price</label>
                        <input type="number" name="price" step="0.01" min="0" required class="border rounded px-2 py-1 w-28 text-right">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">Station</label>
                        <select name="station" required class="border rounded px-2 py-1">
                            <option value="kitchen">kitchen</option>
                            <option value="cashier">cashier</option>
                        </select>
                    </div>
                    <button class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm rounded px-4 py-1">Add</button>
                </form>
            </div>
        </section>
</div>
@endsection
