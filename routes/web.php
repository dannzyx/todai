<?php

use App\Http\Controllers\ProjectController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    // Projects (Dutch URLs, English route names).
    Route::get('projecten', [ProjectController::class, 'index'])->name('projects.index');
    Route::post('projecten', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('projecten/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::put('projecten/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::patch('projecten/{project}/archiveren', [ProjectController::class, 'archive'])->name('projects.archive');
    Route::patch('projecten/{project}/herstellen', [ProjectController::class, 'unarchive'])->name('projects.unarchive');
});

require __DIR__.'/settings.php';
