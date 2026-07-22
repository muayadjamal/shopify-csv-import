<?php

use App\Http\Controllers\ImportController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::get('imports', [ImportController::class, 'index'])->name('imports.index');
    Route::get('imports/create', [ImportController::class, 'create'])->name('imports.create');
    Route::post('imports', [ImportController::class, 'store'])->name('imports.store');
    Route::get('imports/{importRun}', [ImportController::class, 'show'])->name('imports.show');
    Route::get('imports/{importRun}/status', [ImportController::class, 'status'])->name('imports.status');
});

require __DIR__.'/settings.php';
