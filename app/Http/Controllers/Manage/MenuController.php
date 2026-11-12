<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\Category;

class MenuController extends Controller
{
    public function index()
    {
        $categories = Category::orderBy('sort_order')->orderBy('name')->get();
        $items = Category::with(['items' => fn ($q) => $q->orderBy('name')])
            ->orderBy('sort_order')->orderBy('name')->get();

        return view('manage.menu', compact('categories', 'items'));
    }
}
