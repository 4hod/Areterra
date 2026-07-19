<?php

use App\Http\Controllers\AnimalController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EndOfDayController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\TodayController;
use App\Http\Controllers\WelfareCheckController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'show'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware(['auth', 'can:access_hub'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/today', TodayController::class)->name('today');

    Route::get('/members', [MemberController::class, 'index'])
        ->middleware('can:view_members')->name('members.index');
    Route::get('/members/{member}', [MemberController::class, 'show'])
        ->middleware('can:view_members')->name('members.show');
    Route::post('/members', [MemberController::class, 'store'])
        ->middleware('can:create_members')->name('members.store');
    Route::put('/members/{member}', [MemberController::class, 'update'])
        ->middleware('can:edit_members')->name('members.update');

    Route::get('/animals', [AnimalController::class, 'index'])
        ->middleware('can:view_animals')->name('animals.index');
    Route::get('/animals/{animal}', [AnimalController::class, 'show'])
        ->middleware('can:view_animals')->name('animals.show');
    Route::post('/animals', [AnimalController::class, 'store'])
        ->middleware('can:edit_animals')->name('animals.store');
    Route::put('/animals/{animal}', [AnimalController::class, 'update'])
        ->middleware('can:edit_animals')->name('animals.update');

    Route::post('/animals/{animal}/welfare-checks', [WelfareCheckController::class, 'store'])
        ->middleware('can:log_welfare')->name('welfare.store');
    Route::post('/welfare-checks/species', [WelfareCheckController::class, 'storeSpecies'])
        ->middleware('can:log_welfare')->name('welfare.species');
    Route::post('/animals/{animal}/monitoring', [MonitoringController::class, 'store'])
        ->middleware('can:log_welfare')->name('monitoring.store');

    Route::get('/register', [RegisterController::class, 'index'])
        ->middleware('can:log_sessions')->name('register');
    Route::post('/register/{member}/check-in', [RegisterController::class, 'checkIn'])
        ->middleware('can:log_sessions')->name('register.check-in');
    Route::put('/register/{member}', [RegisterController::class, 'update'])
        ->middleware('can:log_sessions')->name('register.update');

    Route::get('/end-of-day', [EndOfDayController::class, 'index'])
        ->middleware('can:log_sessions')->name('end-of-day');
    Route::post('/end-of-day/{member}', [EndOfDayController::class, 'store'])
        ->middleware('can:log_sessions')->name('end-of-day.store');
});
