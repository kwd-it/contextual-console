<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\IssuesController;
use App\Http\Controllers\SourceStatusController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('sources.index');
});

Route::get('/login', [LoginController::class, 'show'])
    ->name('login');

Route::post('/login', [LoginController::class, 'store'])
    ->name('login.store');

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/issues', [IssuesController::class, 'index'])
        ->name('issues.index');

    Route::get('/sources', [SourceStatusController::class, 'index'])
        ->name('sources.index');

    Route::get('/sources/{source}', [SourceStatusController::class, 'show'])
        ->name('sources.show');

    Route::get('/sources/{source}/runs/{run}', [SourceStatusController::class, 'showRun'])
        ->name('sources.runs.show')
        ->scopeBindings();
});
