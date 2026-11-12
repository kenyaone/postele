<?php

use App\Http\Controllers\Auth\PinLoginController;
use App\Http\Controllers\Manage\CategoryController;
use App\Http\Controllers\Manage\ItemController;
use App\Http\Controllers\Manage\MenuController;
use App\Http\Controllers\Pos\PosController;
use App\Http\Controllers\Pos\ShiftController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (! Auth::check()) return redirect()->route('login');
    return match (Auth::user()->role) {
        'manager' => redirect()->route('manage.index'),
        default   => redirect()->route('pos.index'),
    };
})->name('home');

Route::get('/login',   [PinLoginController::class, 'show'])->name('login');
Route::post('/login',  [PinLoginController::class, 'store'])->name('login.store');
Route::post('/logout', [PinLoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'role:cashier,waiter,manager'])->prefix('pos')->name('pos.')->group(function () {
    Route::get('/',                                      [PosController::class, 'index'])->name('index');
    Route::post('/tickets',                              [PosController::class, 'startTicket'])->name('tickets.store');
    Route::get('/tickets/{ticket}',                      [PosController::class, 'show'])->name('tickets.show');
    Route::post('/tickets/{ticket}/items',               [PosController::class, 'addItem'])->name('tickets.items.store');
    Route::patch('/tickets/{ticket}/items/{ticketItem}', [PosController::class, 'updateLine'])->name('tickets.items.update');
    Route::post('/tickets/{ticket}/send',                [PosController::class, 'sendToKitchen'])->name('tickets.send');
    Route::post('/tickets/{ticket}/bill',                [PosController::class, 'printBill'])->name('tickets.bill');
    Route::post('/tickets/{ticket}/close',               [PosController::class, 'closeBill'])->name('tickets.close');
    Route::post('/tickets/{ticket}/void',                [PosController::class, 'voidTicket'])->name('tickets.void');

    Route::get('/shift/open',  [ShiftController::class, 'openForm'])->name('shift.openForm');
    Route::post('/shift/open', [ShiftController::class, 'open'])->name('shift.open');
    Route::get('/shift',       [ShiftController::class, 'show'])->name('shift.show');
    Route::post('/shift/close',[ShiftController::class, 'close'])->name('shift.close');
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
