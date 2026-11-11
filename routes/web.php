<?php

use App\Http\Controllers\Auth\PinLoginController;
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

Route::middleware('auth')->group(function () {
    Route::get('/pos', fn () => view('pos.index'))
        ->middleware('role:cashier,waiter,manager')
        ->name('pos.index');

    Route::get('/manage', fn () => view('manage.index'))
        ->middleware('role:manager')
        ->name('manage.index');
});
