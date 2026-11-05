<?php

declare(strict_types=1);

use Codeberry\Beads\Http\Controllers\Api\BootstrapController;
use Codeberry\Beads\Http\Controllers\Api\IssuesController;
use Codeberry\Beads\Http\Controllers\Api\SnapshotController;
use Codeberry\Beads\Http\Controllers\SpaController;
use Codeberry\Beads\Http\Middleware\EnsureHealthy;
use Codeberry\Beads\Support\IssueId;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->name('api.')->group(function (): void {
    Route::get('bootstrap', BootstrapController::class)->name('bootstrap');

    Route::middleware(EnsureHealthy::class)->group(function (): void {
        Route::get('snapshot', SnapshotController::class)->name('snapshot');
        Route::get('issues/{id}', [IssuesController::class, 'show'])->where('id', IssueId::PATTERN)->name('issues.show');
        Route::post('issues', [IssuesController::class, 'store'])->name('issues.store');
    });

    Route::any('{any?}', fn () => response()->json(['error' => ['code' => 'not_found', 'message' => 'Unknown API endpoint.']], 404))
        ->where('any', '.*')
        ->name('fallback');
});

Route::get('/{view?}', SpaController::class)
    ->where('view', '^(?!api(?:/|$)).*$')
    ->name('spa');
