<?php

use App\Http\Controllers\Admin\SpendReportController;
use App\Http\Controllers\CreditPurchaseController;
use App\Http\Controllers\GenerationController;
use App\Http\Controllers\GenerationEstimateController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/generations')->name('home');

Route::middleware('auth')->group(function () {
    Route::get('generations', [GenerationController::class, 'index'])->name('generations.index');
    // Protects the account-wide daily quota from one user's double clicks.
    Route::post('generations', [GenerationController::class, 'store'])->middleware('throttle:10,1')->name('generations.store');
    Route::post('generations/estimate', GenerationEstimateController::class)->name('generations.estimate');
    Route::post('credits', [CreditPurchaseController::class, 'store'])->middleware('throttle:10,1')->name('credits.store');

    Route::get('admin/spend', SpendReportController::class)->can('view-spend-report')->name('admin.spend');
});
