<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ApiDocsPortalController;
use App\Http\Controllers\ApiDocsFilteredSpecController;
use App\Http\Controllers\Api\MonitoringHealthController;
use App\Http\Middleware\ApiDocsAccessMiddleware;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/health', [MonitoringHealthController::class, 'liveness'])
    ->name('health.liveness');

Route::get('/dashboard', function () {
    return redirect('/admin/dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['web', 'throttle:api-docs', ApiDocsAccessMiddleware::class])
    ->get('/docs/api.filtered.json', ApiDocsFilteredSpecController::class)
    ->name('docs.api.filtered');

Route::middleware(['web', 'throttle:api-docs', ApiDocsAccessMiddleware::class])
    ->get('/docs/api/portal', ApiDocsPortalController::class)
    ->name('docs.api.portal');

require __DIR__.'/auth.php';
