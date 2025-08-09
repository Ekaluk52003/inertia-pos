<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\EventController;
use App\Http\Controllers\ProfileController;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Event routes - authenticated and verified email required
Route::middleware(['auth', 'verified'])->group(function () {
    // List all events
    Route::get('/events', [EventController::class, 'index'])->name('events.index');
    // Show create event form
    Route::get('/events/create', [EventController::class, 'create'])->name('events.create');
    // Store a new event
    Route::post('/events', [EventController::class, 'store'])->name('events.store');
    // Show a specific event
    Route::get('/events/{event}', [EventController::class, 'show'])->name('events.show');
    // Show edit event form
    Route::get('/events/{event}/edit', [EventController::class, 'edit'])->name('events.edit');
    // Update a specific event
    Route::put('/events/{event}', [EventController::class, 'update'])->name('events.update');
    // Delete a specific event
    Route::delete('/events/{event}', [EventController::class, 'destroy'])->name('events.destroy');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
