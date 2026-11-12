<?php

use App\Http\Controllers\Api\PrintBridgeController;
use Illuminate\Support\Facades\Route;

Route::middleware('bridge')->prefix('print')->group(function () {
    Route::get('/jobs/pending',      [PrintBridgeController::class, 'pending']);
    Route::patch('/jobs/{printJob}', [PrintBridgeController::class, 'update']);
});
