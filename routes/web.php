<?php

use App\Http\Controllers\Admin\CottageController as AdminCottageController;
use App\Http\Controllers\CottageAvailabilityController;
use App\Http\Controllers\CottageController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Guest\BookingController as GuestBookingController;
use App\Http\Controllers\Guest\DashboardController as GuestDashboardController;
use App\Http\Controllers\Guest\ReservationController;
use App\Http\Controllers\Staff\BookingController as StaffBookingController;
use App\Http\Controllers\Staff\DashboardController as StaffDashboardController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public
|--------------------------------------------------------------------------
*/
Route::view('/', 'welcome')->name('home');

Route::get('/cottages', [CottageController::class, 'index'])->name('cottages.index');
Route::get('/cottages/{cottage}', [CottageController::class, 'show'])->name('cottages.show');

Route::middleware('throttle:60,1')->group(function () {
    Route::get('/cottages/{cottage}/availability', [CottageAvailabilityController::class, 'month'])->name('cottages.availability');
    Route::get('/cottages/{cottage}/quote', [CottageAvailabilityController::class, 'quote'])->name('cottages.quote');
});

/*
|--------------------------------------------------------------------------
| Authenticated – shared
|--------------------------------------------------------------------------
| /dashboard is a dispatcher: it sends each user to their own role's area.
*/
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

/*
|--------------------------------------------------------------------------
| Guest (resort customer) area
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'role:guest'])->name('guest.')->group(function () {
    // Reservation form lives next to the cottage URL; logged-out visitors are
    // sent to log in and then returned here with their dates intact.
    Route::get('/cottages/{cottage}/book', [ReservationController::class, 'create'])->name('reservations.create');
    Route::post('/cottages/{cottage}/book', [ReservationController::class, 'store'])
        ->middleware('throttle:10,1')->name('reservations.store');

    Route::prefix('my')->group(function () {
        Route::get('/', GuestDashboardController::class)->name('dashboard');
        Route::get('/bookings', [GuestBookingController::class, 'index'])->name('bookings.index');
        Route::get('/bookings/{booking}', [GuestBookingController::class, 'show'])->name('bookings.show');
        Route::post('/bookings/{booking}/cancel', [GuestBookingController::class, 'cancel'])->name('bookings.cancel');
    });
});

/*
|--------------------------------------------------------------------------
| Staff area (owner/admin may also enter to cover front-desk duties)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'role:staff,admin'])
    ->prefix('staff')->name('staff.')
    ->group(function () {
        Route::get('/', StaffDashboardController::class)->name('dashboard');

        Route::get('/bookings', [StaffBookingController::class, 'index'])->name('bookings.index');
        Route::get('/bookings/{booking}', [StaffBookingController::class, 'show'])->name('bookings.show');
        Route::post('/bookings/{booking}/approve', [StaffBookingController::class, 'approve'])->name('bookings.approve');
        Route::post('/bookings/{booking}/reject', [StaffBookingController::class, 'reject'])->name('bookings.reject');
        Route::post('/bookings/{booking}/check-in', [StaffBookingController::class, 'checkIn'])->name('bookings.check-in');
        Route::post('/bookings/{booking}/check-out', [StaffBookingController::class, 'checkOut'])->name('bookings.check-out');
        Route::post('/bookings/{booking}/cancel', [StaffBookingController::class, 'cancel'])->name('bookings.cancel');
    });

/*
|--------------------------------------------------------------------------
| Admin (owner) area
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'role:admin'])
    ->prefix('admin')->name('admin.')
    ->group(function () {
        Route::view('/', 'admin.dashboard')->name('dashboard');

        Route::resource('cottages', AdminCottageController::class)->except('show');
        Route::patch('cottages/{cottage}/toggle', [AdminCottageController::class, 'toggle'])->name('cottages.toggle');
    });

require __DIR__.'/auth.php';
