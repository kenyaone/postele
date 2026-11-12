<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:100',
            'sort_order' => 'nullable|integer|min:0|max:9999',
        ]);
        Category::create([
            'name'       => $data['name'],
            'sort_order' => $data['sort_order'] ?? (Category::max('sort_order') + 1),
            'active'     => true,
        ]);
        return back()->with('ok', 'Category added.');
    }

    public function update(Request $request, Category $category)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:100',
            'sort_order' => 'required|integer|min:0|max:9999',
            'active'     => 'nullable|boolean',
        ]);
        $category->update([
            'name'       => $data['name'],
            'sort_order' => $data['sort_order'],
            'active'     => (bool) ($data['active'] ?? false),
        ]);
        return back()->with('ok', "Category \u{201C}{$category->name}\u{201D} updated.");
    }

    public function destroy(Category $category)
    {
        if ($category->items()->exists()) {
            return back()->withErrors(['delete' => "Can\u{2019}t delete \u{201C}{$category->name}\u{201D} \u{2014} it still has items. Move or delete the items first."]);
        }
        $name = $category->name;
        $category->delete();
        return back()->with('ok', "Category \u{201C}$name\u{201D} deleted.");
    }
}
