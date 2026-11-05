<?php

declare(strict_types=1);

use Codeberry\Beads\Http\Controllers\SpaController;
use Illuminate\Support\Facades\Route;

Route::get('/{view?}', SpaController::class)
    ->where('view', '^(?!api(?:/|$)).*$')
    ->name('spa');
