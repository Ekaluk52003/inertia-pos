<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\BillController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QrCodeController;
use App\Http\Controllers\RestaurantController;
use App\Http\Controllers\StaffController;

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

// Eattinee routes - authenticated and verified email required
Route::middleware(['auth', 'verified'])->group(function () {
    // Restaurant routes
    Route::get('/restaurants', [RestaurantController::class, 'index'])->name('restaurants.index');
    Route::get('/restaurants/create', [RestaurantController::class, 'create'])->name('restaurants.create');
    Route::post('/restaurants', [RestaurantController::class, 'store'])->name('restaurants.store');
    Route::get('/restaurants/{restaurant}', [RestaurantController::class, 'show'])->name('restaurants.show');
    Route::get('/restaurants/{restaurant}/edit', [RestaurantController::class, 'edit'])->name('restaurants.edit');
    Route::put('/restaurants/{restaurant}', [RestaurantController::class, 'update'])->name('restaurants.update');
    Route::delete('/restaurants/{restaurant}', [RestaurantController::class, 'destroy'])->name('restaurants.destroy');
    
    // Restaurant-specific routes
    Route::prefix('restaurants/{restaurant}')->group(function () {
        // Menu routes
        Route::get('menu', [MenuController::class, 'index'])->name('menu.index');
        Route::get('menu/create', [MenuController::class, 'create'])->name('menu.create');
        Route::post('menu', [MenuController::class, 'store'])->name('menu.store');
        Route::get('menu/{menu}/edit', [MenuController::class, 'edit'])->name('menu.edit');
        Route::put('menu/{menu}', [MenuController::class, 'update'])->name('menu.update');
        Route::delete('menu/{menu}', [MenuController::class, 'destroy'])->name('menu.destroy');
        Route::patch('menu/{menu}/toggle', [MenuController::class, 'toggleAvailability'])->name('menu.toggle');
        
        // QR Code routes
        Route::get('qrcodes', [QrCodeController::class, 'index'])->name('qrcodes.index');
        Route::get('qrcodes/create', [QrCodeController::class, 'create'])->name('qrcodes.create');
        Route::post('qrcodes', [QrCodeController::class, 'store'])->name('qrcodes.store');
        Route::get('qrcodes/{qrCode}', [QrCodeController::class, 'show'])->name('qrcodes.show');
        Route::patch('qrcodes/{qrCode}/toggle', [QrCodeController::class, 'toggleActive'])->name('qrcodes.toggle');
        Route::delete('qrcodes/{qrCode}', [QrCodeController::class, 'destroy'])->name('qrcodes.destroy');
        Route::post('qrcodes/{qrCode}/regenerate', [QrCodeController::class, 'regenerate'])->name('qrcodes.regenerate');
        
        // Order routes
        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::patch('orders/{order}/item-status', [OrderController::class, 'updateItemStatus'])->name('orders.update-item-status');
        Route::patch('orders/{order}/mark-paid', [OrderController::class, 'markAsPaid'])->name('orders.mark-paid');
        Route::get('kitchen', [OrderController::class, 'kitchenView'])->name('kitchen.show');
        
        // Bill routes
        Route::get('bills', [BillController::class, 'index'])->name('bills.index');
        Route::get('bills/{bill}', [BillController::class, 'show'])->name('bills.show');
        Route::post('orders/{order}/generate-bill', [BillController::class, 'generate'])->name('bills.generate');
        Route::patch('bills/{bill}/status', [BillController::class, 'updateStatus'])->name('bills.update-status');
        
        // Payment routes
        Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::get('payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
        
        // Staff routes
        Route::get('staff', [StaffController::class, 'index'])->name('staff.index');
        Route::get('staff/create', [StaffController::class, 'create'])->name('staff.create');
        Route::post('staff', [StaffController::class, 'store'])->name('staff.store');
        Route::delete('staff/{user}', [StaffController::class, 'destroy'])->name('staff.destroy');
    });
});

// Public routes for customers
Route::prefix('public')->group(function () {
    // Menu access via QR code
    Route::get('menu/{restaurantCode}/{tableCode}', [MenuController::class, 'publicMenu'])->name('public.menu');
    
    // Order creation
    Route::post('order/{restaurantCode}/{tableCode}', [OrderController::class, 'storeFromMenu'])->name('public.order.store');
    
    // Order status tracking
    Route::get('order/{orderCode}/status', [OrderController::class, 'getOrderStatus'])->name('public.order.status');
    
    // Bill request
    Route::post('order/{orderCode}/bill', [BillController::class, 'requestBill'])->name('public.bill.request');
    
    // Payment processing
    Route::post('order/{orderCode}/pay', [PaymentController::class, 'processPayment'])->name('public.payment.process');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
