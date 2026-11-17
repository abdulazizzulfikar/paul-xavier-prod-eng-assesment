<?php

use App\Http\Controllers\GenerationController;
use App\Http\Controllers\PixelforgeWebhookController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware('auth')->group(function () {
    Route::redirect('dashboard', 'generations')->name('dashboard');

    Route::get('generations', [GenerationController::class, 'index'])->name('generations.index');
    Route::get('generations/create', [GenerationController::class, 'create'])->name('generations.create');
    Route::get('generations/estimate', [GenerationController::class, 'estimate'])->name('generations.estimate');
    Route::post('generations', [GenerationController::class, 'store'])->name('generations.store');
});

// Excluded from CSRF in bootstrap/app.php: Pixelforge authenticates with a signature instead.
Route::post('webhooks/pixelforge', PixelforgeWebhookController::class)->name('webhooks.pixelforge');

require __DIR__.'/settings.php';
