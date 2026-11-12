<?php

use App\Http\Controllers\Api\KopoKopoController;
use App\Http\Controllers\Api\PrintBridgeController;
use Illuminate\Support\Facades\Route;

// Kopo Kopo calls these; each request must be signed (X-KopoKopo-Signature)
Route::post('/kk/webhook',      [KopoKopoController::class, 'webhook'])->name('kk.webhook');
Route::post('/kk/stk-callback', [KopoKopoController::class, 'stkCallback'])->name('kk.stk.callback');

Route::middleware('bridge')->prefix('print')->group(function () {
    Route::get('/jobs/pending',      [PrintBridgeController::class, 'pending']);
    Route::patch('/jobs/{printJob}', [PrintBridgeController::class, 'update']);
});
