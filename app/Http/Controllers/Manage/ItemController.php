<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\Item;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name'        => 'required|string|max:120',
            'price'       => 'required|numeric|min:0|max:999999',
            'station'     => 'required|in:kitchen,cashier',
        ]);
        Item::create([
            'category_id' => $data['category_id'],
            'name'        => $data['name'],
            'price'       => $data['price'],
            'station'     => $data['station'],
            'active'      => true,
        ]);
        return back()->with('ok', "Item \u{201C}{$data['name']}\u{201D} added.");
    }

    public function update(Request $request, Item $item)
    {
        $data = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name'        => 'required|string|max:120',
            'price'       => 'required|numeric|min:0|max:999999',
            'station'     => 'required|in:kitchen,cashier',
            'active'      => 'nullable|boolean',
        ]);
        $item->update([
            'category_id' => $data['category_id'],
            'name'        => $data['name'],
            'price'       => $data['price'],
            'station'     => $data['station'],
            'active'      => (bool) ($data['active'] ?? false),
        ]);
        return back()->with('ok', "Item \u{201C}{$item->name}\u{201D} updated.");
    }

    public function destroy(Item $item)
    {
        try {
            $name = $item->name;
            $item->delete();
            return back()->with('ok', "Item \u{201C}$name\u{201D} deleted.");
        } catch (QueryException $e) {
            return back()->withErrors(['delete' => "Can\u{2019}t delete \u{201C}{$item->name}\u{201D} \u{2014} it\u{2019}s been sold on a past ticket. Mark it Inactive instead."]);
        }
    }
}
