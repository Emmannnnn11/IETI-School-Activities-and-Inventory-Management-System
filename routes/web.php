<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InventoryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return redirect()->route('login');
});

// Authentication Routes
Route::get('login', [App\Http\Controllers\Auth\LoginController::class, 'showLoginForm'])->name('login');
Route::post('login', [App\Http\Controllers\Auth\LoginController::class, 'login']);
Route::post('logout', [App\Http\Controllers\Auth\LoginController::class, 'logout'])->name('logout');

// Password Reset Routes
Route::get('password/reset', [App\Http\Controllers\Auth\ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
Route::post('password/email', [App\Http\Controllers\Auth\ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email');
Route::get('password/reset/{token}', [App\Http\Controllers\Auth\ResetPasswordController::class, 'showResetForm'])->name('password.reset');
Route::post('password/reset', [App\Http\Controllers\Auth\ResetPasswordController::class, 'reset'])->name('password.update');

Route::get('/home', [HomeController::class, 'index'])->name('home');

// Profile routes
Route::middleware(['auth'])->group(function () {
    Route::get('/profile', [App\Http\Controllers\ProfileController::class, 'index'])->name('profile.index');
    Route::get('/profile/edit', [App\Http\Controllers\ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [App\Http\Controllers\ProfileController::class, 'update'])->name('profile.update');
    Route::get('/profile/password', [App\Http\Controllers\ProfileController::class, 'editPassword'])->name('profile.password');
    Route::put('/profile/password', [App\Http\Controllers\ProfileController::class, 'updatePassword'])->name('profile.password');
});

// Event routes
Route::middleware(['auth'])->group(function () {
    Route::get('events/history', [EventController::class, 'history'])->name('events.history');
    Route::get('events/history/export', [EventController::class, 'exportHistory'])->name('events.history.export');
    Route::resource('events', EventController::class);
    
    // Event approval routes
    Route::post('/events/{event}/approve', [EventController::class, 'approve'])
        ->name('events.approve')
        ->middleware('can:approve,event');
    
    Route::post('/events/{event}/reject', [EventController::class, 'reject'])
        ->name('events.reject')
        ->middleware('can:approve,event');
    
    // Return item route
    Route::post('/event-items/{eventItem}/return', [EventController::class, 'returnItem'])
        ->name('event-items.return')
        ->middleware('can:manage-inventory');
});

// ✅ Inventory routes (fixed parameter binding)
Route::middleware(['auth', 'can:manage-inventory'])->group(function () {
    // Export inventory borrowing records (must be defined before the resource route)
    Route::get('inventory/export', [InventoryController::class, 'exportBorrowingRecords'])
        ->name('inventory.export');

    Route::resource('inventory', InventoryController::class)->parameters([
        'inventory' => 'inventoryItem'
    ]);
    
    // Category editing routes
    Route::get('/inventory/category/{category}/edit', [InventoryController::class, 'editCategory'])
        ->name('inventory.category.edit');
    Route::put('/inventory/category/{category}', [InventoryController::class, 'updateCategory'])
        ->name('inventory.category.update');
});

// User management routes (admin only)
Route::middleware(['auth'])->group(function () {
    Route::resource('users', App\Http\Controllers\UserController::class);
});

// API routes for calendar
Route::middleware(['auth'])->group(function () {
    Route::get('/api/events', function () {
        // Active/upcoming events only:
        // - end_datetime (event_date + end_time) must be strictly greater than now
        // - rejected events belong in history, not in the active calendar
        $events = \App\Models\Event::with(['creator', 'approver', 'eventItems.inventoryItem'])
            ->where('status', '!=', 'rejected')
            ->future()
            ->orderBy('event_date', 'asc')
            ->get();
        
        return response()->json($events->map(function ($event) {
            return [
                'id' => $event->id,
                'title' => $event->title,
                'start' => $event->event_date->format('Y-m-d'),
                'color' => $event->status_color,
                'status' => $event->status,
                'location' => $event->location,
                'start_time' => $event->start_time,
                'end_time' => $event->end_time,
                'description' => $event->description,
                'creator' => $event->creator->name ?? 'Unknown',
            ];
        }));
    });
});
