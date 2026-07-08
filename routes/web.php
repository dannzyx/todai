<?php

use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TaskController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Vandaag is the landing for authenticated users; guests see the marketing page.
Route::get('/', function (Request $request) {
    if ($request->user()) {
        return app(TaskController::class)->today($request);
    }

    return Inertia::render('Welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    // Post-login landing (Fortify redirects here) also shows Vandaag.
    Route::get('dashboard', [TaskController::class, 'today'])->name('dashboard');

    // Tasks (Dutch URLs, English route names).
    Route::get('inbox', [TaskController::class, 'inbox'])->name('tasks.inbox');
    Route::post('taken', [TaskController::class, 'store'])->name('tasks.store');
    Route::put('taken/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::patch('taken/{task}/verplaatsen', [TaskController::class, 'move'])->name('tasks.move');
    Route::patch('taken/{task}/datum', [TaskController::class, 'setDueDate'])->name('tasks.due-date');
    Route::patch('taken/{task}/status', [TaskController::class, 'toggle'])->name('tasks.toggle');
    Route::delete('taken/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');

    // Projects (Dutch URLs, English route names).
    Route::get('projecten', [ProjectController::class, 'index'])->name('projects.index');
    Route::post('projecten', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('projecten/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::put('projecten/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::patch('projecten/{project}/archiveren', [ProjectController::class, 'archive'])->name('projects.archive');
    Route::patch('projecten/{project}/herstellen', [ProjectController::class, 'unarchive'])->name('projects.unarchive');
});

require __DIR__.'/settings.php';
