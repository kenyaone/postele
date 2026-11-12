<?php

use App\Http\Controllers\Auth\PinLoginController;
use App\Http\Controllers\Manage\CategoryController;
use App\Http\Controllers\Manage\DashboardController;
use App\Http\Controllers\Manage\ExpenseController;
use App\Http\Controllers\Manage\ItemController;
use App\Http\Controllers\Manage\HrController;
use App\Http\Controllers\Manage\MenuController;
use App\Http\Controllers\Manage\UserController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\YieldController;
use App\Http\Controllers\Manage\ProcurementController;
use App\Http\Controllers\Manage\ReportController;
use App\Http\Controllers\Manage\SettingsController;
use App\Http\Controllers\Pos\MpesaController;
use App\Http\Controllers\Pos\PosController;
use App\Http\Controllers\Pos\ShiftController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (! Auth::check()) return redirect()->route('login');
    return match (Auth::user()->role) {
        'manager' => redirect()->route('manage.index'),
        'stock'   => redirect()->route('stock.count'),
        default   => redirect()->route('pos.index'),
    };
})->name('home');

Route::get('/login',   [PinLoginController::class, 'show'])->name('login');
Route::post('/login',  [PinLoginController::class, 'store'])->middleware('throttle:10,1')->name('login.store');
Route::post('/logout', [PinLoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'role:cashier,waiter,manager'])->prefix('pos')->name('pos.')->group(function () {
    Route::get('/bills', [\App\Http\Controllers\Pos\BillsController::class, 'index'])->name('bills');
    Route::get('/',                                      [PosController::class, 'index'])->name('index');
    Route::post('/tickets',                              [PosController::class, 'startTicket'])->name('tickets.store');
    Route::get('/tickets/{ticket}',                      [PosController::class, 'show'])->name('tickets.show');
    Route::post('/tickets/{ticket}/items',               [PosController::class, 'addItem'])->name('tickets.items.store');
    Route::patch('/tickets/{ticket}/items/{ticketItem}', [PosController::class, 'updateLine'])->name('tickets.items.update');
    Route::post('/tickets/{ticket}/send',                [PosController::class, 'sendToKitchen'])->name('tickets.send');
    Route::get('/tickets/{ticket}/bill',                 [PosController::class, 'printBill'])->name('tickets.bill');
    Route::post('/tickets/{ticket}/close',               [PosController::class, 'closeBill'])->name('tickets.close');
    Route::post('/tickets/{ticket}/void',                [PosController::class, 'voidTicket'])->middleware('throttle:10,1')->name('tickets.void');
    Route::post('/tickets/{ticket}/mpesa/stk',    [MpesaController::class, 'stk'])->middleware('throttle:6,1')->name('tickets.mpesa.stk');
    Route::get('/tickets/{ticket}/mpesa/status',  [MpesaController::class, 'stkStatus'])->name('tickets.mpesa.status');
    Route::post('/tickets/{ticket}/mpesa/match',  [MpesaController::class, 'match'])->name('tickets.mpesa.match');
    Route::post('/tickets/{ticket}/mpesa/manual', [MpesaController::class, 'manual'])->name('tickets.mpesa.manual');

    Route::get('/shift/open',  [ShiftController::class, 'openForm'])->name('shift.openForm');
    Route::post('/shift/open', [ShiftController::class, 'open'])->name('shift.open');
    Route::get('/shift',       [ShiftController::class, 'show'])->name('shift.show');
    Route::post('/shift/close',[ShiftController::class, 'close'])->name('shift.close');
});

Route::middleware(['auth', 'role:manager'])->prefix('manage')->name('manage.')->group(function () {
    Route::get('/mpesa', [\App\Http\Controllers\Manage\MpesaLogController::class, 'index'])->name('mpesa');
    Route::get('/voids', [\App\Http\Controllers\Manage\VoidsController::class, 'index'])->name('voids');
    Route::get('/', [DashboardController::class, 'index'])->name('index');
    Route::get('/menu', [MenuController::class, 'index'])->name('menu');
    Route::get('/settings',   [SettingsController::class, 'edit'])->name('settings');
    Route::patch('/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::patch('/settings/password', [SettingsController::class, 'updatePassword'])->name('password.update');

    Route::get('/expenses',                 [ExpenseController::class, 'index'])->name('expenses.index');
    Route::post('/expenses',                [ExpenseController::class, 'store'])->name('expenses.store');
    Route::get('/expenses/{expense}/edit',  [ExpenseController::class, 'edit'])->name('expenses.edit');
    Route::patch('/expenses/{expense}',     [ExpenseController::class, 'update'])->name('expenses.update');
    Route::delete('/expenses/{expense}',    [ExpenseController::class, 'destroy'])->name('expenses.destroy');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports');

    Route::get('/hr',                         [HrController::class, 'index'])->name('hr');
    Route::post('/hr/staff',                  [HrController::class, 'storeStaff'])->name('hr.staff.store');
    Route::get('/hr/staff/{staff}',           [HrController::class, 'showStaff'])->name('hr.staff');
    Route::patch('/hr/staff/{staff}',         [HrController::class, 'updateStaff'])->name('hr.staff.update');
    Route::post('/hr/staff/{staff}/payments', [HrController::class, 'storePayment'])->name('hr.payments.store');
    Route::delete('/hr/payments/{payment}',   [HrController::class, 'destroyPayment'])->name('hr.payments.destroy');

    Route::get('/users',              [UserController::class, 'index'])->name('users.index');
    Route::post('/users',             [UserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}/edit',  [UserController::class, 'edit'])->name('users.edit');
    Route::patch('/users/{user}',     [UserController::class, 'update'])->name('users.update');

    Route::get('/procurement',                            [ProcurementController::class, 'index'])->name('procurement');
    Route::post('/procurement/purchases',                 [ProcurementController::class, 'storePurchase'])->name('procurement.purchases.store');
    Route::get('/procurement/purchases/{purchase}',       [ProcurementController::class, 'showPurchase'])->name('procurement.purchase');
    Route::patch('/procurement/purchases/{purchase}',     [ProcurementController::class, 'updatePurchase'])->name('procurement.purchases.update');
    Route::delete('/procurement/purchases/{purchase}',    [ProcurementController::class, 'destroyPurchase'])->name('procurement.purchases.destroy');
    Route::post('/procurement/purchases/{purchase}/pay',  [ProcurementController::class, 'storePayment'])->name('procurement.payments.store');
    Route::delete('/procurement/payments/{payment}',      [ProcurementController::class, 'destroyPayment'])->name('procurement.payments.destroy');
    Route::post('/procurement/suppliers',                 [ProcurementController::class, 'storeSupplier'])->name('procurement.suppliers.store');
    Route::get('/procurement/suppliers/{supplier}',       [ProcurementController::class, 'showSupplier'])->name('procurement.supplier');
    Route::patch('/procurement/suppliers/{supplier}',     [ProcurementController::class, 'updateSupplier'])->name('procurement.suppliers.update');

    Route::post('/categories',              [CategoryController::class, 'store'])->name('categories.store');
    Route::patch('/categories/{category}',  [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

    Route::post('/items',          [ItemController::class, 'store'])->name('items.store');
    Route::patch('/items/{item}',  [ItemController::class, 'update'])->name('items.update');
    Route::delete('/items/{item}', [ItemController::class, 'destroy'])->name('items.destroy');
});

// Stock counting: kitchen/stock staff and managers
Route::middleware(['auth', 'role:stock,manager'])->prefix('stock')->name('stock.')->group(function () {
    Route::get('/',                     [StockController::class, 'count'])->name('count');
    Route::post('/',                    [StockController::class, 'saveCounts'])->name('count.save');
    Route::get('/items',                [StockController::class, 'items'])->name('items');
    Route::post('/items',               [StockController::class, 'storeItem'])->name('items.store');
    Route::patch('/items/{stockItem}',  [StockController::class, 'updateItem'])->name('items.update');
    Route::get('/shopping-list',         [StockController::class, 'printShopping'])->name('shopping.print');
    // Raw material yield vs sales (rules editable by managers only, checked in the controller)
    Route::get('/yield',                    [YieldController::class, 'index'])->name('yield');
    Route::post('/yield/notes',             [YieldController::class, 'storeNote'])->name('yield.notes');
    Route::get('/yield/rules/create',       [YieldController::class, 'create'])->name('yield.rules.create');
    Route::post('/yield/rules',             [YieldController::class, 'store'])->name('yield.rules.store');
    Route::get('/yield/rules/{rule}/edit',  [YieldController::class, 'edit'])->name('yield.rules.edit');
    Route::put('/yield/rules/{rule}',       [YieldController::class, 'update'])->name('yield.rules.update');
});
