<?php

use App\Http\Controllers\ChatController;
use App\Http\Controllers\FirefliesWebhookController;
use App\Http\Controllers\MeetingController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TaskController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Fireflies webhook — machine-to-machine, no auth; the token identifies the user.
Route::post('webhooks/fireflies/{token}', FirefliesWebhookController::class)
    ->name('fireflies.webhook');

// No marketing front-page: authenticated users go to their dashboard, guests to login.
Route::get('/', function (Request $request) {
    return redirect()->route($request->user() ? 'dashboard' : 'login');
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

    // AI project suggestions (Phase 3).
    Route::patch('taken/{task}/suggestie', [TaskController::class, 'suggest'])->name('tasks.suggest');
    Route::patch('taken/{task}/suggestie/toewijzen', [TaskController::class, 'acceptSuggestion'])->name('tasks.suggestion.accept');
    Route::patch('taken/{task}/suggestie/negeren', [TaskController::class, 'dismissSuggestion'])->name('tasks.suggestion.dismiss');

    // Chat (Phase 4).
    Route::get('chat', [ChatController::class, 'index'])->name('chat.index');
    Route::post('chat', [ChatController::class, 'send'])->name('chat.send');
    Route::post('chat/nieuw', [ChatController::class, 'reset'])->name('chat.reset');

    // Projects (Dutch URLs, English route names).
    Route::get('projecten', [ProjectController::class, 'index'])->name('projects.index');
    Route::post('projecten', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('projecten/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::put('projecten/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::patch('projecten/{project}/archiveren', [ProjectController::class, 'archive'])->name('projects.archive');
    Route::patch('projecten/{project}/herstellen', [ProjectController::class, 'unarchive'])->name('projects.unarchive');

    // Meetings (Dutch URLs, English route names).
    Route::get('vergaderingen', [MeetingController::class, 'index'])->name('meetings.index');
    Route::post('vergaderingen', [MeetingController::class, 'store'])->name('meetings.store');
    Route::get('vergaderingen/{meeting}', [MeetingController::class, 'show'])->name('meetings.show');
    Route::put('vergaderingen/{meeting}', [MeetingController::class, 'update'])->name('meetings.update');
    Route::delete('vergaderingen/{meeting}', [MeetingController::class, 'destroy'])->name('meetings.destroy');
    Route::post('vergaderingen/{meeting}/genereren', [MeetingController::class, 'generate'])->name('meetings.generate');

    // Meeting todo suggestions (staged until accepted). Scoped to their meeting.
    Route::scopeBindings()->group(function () {
        Route::patch('vergaderingen/{meeting}/suggesties/toewijzen', [MeetingController::class, 'acceptAllSuggestions'])->name('meetings.suggestions.accept-all');
        Route::patch('vergaderingen/{meeting}/suggesties/{taskSuggestion}/toewijzen', [MeetingController::class, 'acceptSuggestion'])->name('meetings.suggestion.accept');
        Route::patch('vergaderingen/{meeting}/suggesties/{taskSuggestion}/negeren', [MeetingController::class, 'dismissSuggestion'])->name('meetings.suggestion.dismiss');
    });

    // Meeting project suggestion.
    Route::patch('vergaderingen/{meeting}/project/toewijzen', [MeetingController::class, 'acceptProject'])->name('meetings.project.accept');
    Route::patch('vergaderingen/{meeting}/project/negeren', [MeetingController::class, 'dismissProject'])->name('meetings.project.dismiss');
});

require __DIR__.'/settings.php';
