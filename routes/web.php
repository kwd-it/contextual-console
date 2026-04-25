<?php

use App\Http\Controllers\SourceStatusController;
use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/login', [LoginController::class, 'show'])
    ->name('login');

Route::post('/login', [LoginController::class, 'store'])
    ->name('login.store');

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/sources', [SourceStatusController::class, 'index'])
        ->name('sources.index');

    Route::get('/sources/{source}', [SourceStatusController::class, 'show'])
        ->name('sources.show');
});
