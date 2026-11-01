<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public
|--------------------------------------------------------------------------
*/
Route::view('/', 'welcome')->name('home');

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
Route::middleware(['auth', 'verified', 'role:guest'])
    ->prefix('my')->name('guest.')
    ->group(function () {
        Route::view('/', 'guest.dashboard')->name('dashboard');
    });

/*
|--------------------------------------------------------------------------
| Staff area (owner/admin may also enter to cover front-desk duties)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'role:staff,admin'])
    ->prefix('staff')->name('staff.')
    ->group(function () {
        Route::view('/', 'staff.dashboard')->name('dashboard');
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
    });

require __DIR__.'/auth.php';
