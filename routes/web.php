<?php

use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\VoiceAnalysisController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route('login');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::post('dashboard/analyze', [VoiceAnalysisController::class, 'store'])->name('dashboard.analyze');
    Route::get('dashboard/analyze/{batchId}/status', [VoiceAnalysisController::class, 'status'])->name('dashboard.analyze.status');
    Route::get('dashboard/analyze/{batchId}/audio/{filename}', [VoiceAnalysisController::class, 'audio'])->name('dashboard.analyze.audio');
    Route::get('dashboard/batches', [VoiceAnalysisController::class, 'batches'])->name('dashboard.batches');
    Route::get('dashboard/usage', [VoiceAnalysisController::class, 'usage'])->name('dashboard.usage');

    Route::get('dashboard/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('dashboard/register', [RegisteredUserController::class, 'store']);
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
