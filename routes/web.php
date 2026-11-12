<?php

use App\Http\Controllers\Auth\PinLoginController;
use App\Http\Controllers\Manage\CategoryController;
use App\Http\Controllers\Manage\ItemController;
use App\Http\Controllers\Manage\MenuController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (! Auth::check()) {
        return redirect()->route('login');
    }
    return match (Auth::user()->role) {
        'manager' => redirect()->route('manage.index'),
        default   => redirect()->route('pos.index'),
    };
})->name('home');

Route::get('/login',  [PinLoginController::class, 'show'])->name('login');
Route::post('/login', [PinLoginController::class, 'store'])->name('login.store');
Route::post('/logout',[PinLoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'role:cashier,waiter,manager'])->group(function () {
    Route::get('/pos', fn () => view('pos.index'))->name('pos.index');
});

Route::middleware(['auth', 'role:manager'])->prefix('manage')->name('manage.')->group(function () {
    Route::get('/', fn () => view('manage.index'))->name('index');
    Route::get('/menu', [MenuController::class, 'index'])->name('menu');

    Route::post('/categories',              [CategoryController::class, 'store'])->name('categories.store');
    Route::patch('/categories/{category}',  [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

    Route::post('/items',          [ItemController::class, 'store'])->name('items.store');
    Route::patch('/items/{item}',  [ItemController::class, 'update'])->name('items.update');
    Route::delete('/items/{item}', [ItemController::class, 'destroy'])->name('items.destroy');
});
