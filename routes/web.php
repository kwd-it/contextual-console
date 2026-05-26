<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ChangesController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IssuesController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SourceStatusController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard.index');
});

Route::get('/login', [LoginController::class, 'show'])
    ->name('login');

Route::post('/login', [LoginController::class, 'store'])
    ->name('login.store');

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard.index');

    Route::get('/changes', [ChangesController::class, 'index'])
        ->name('changes.index');

    Route::get('/issues', [IssuesController::class, 'index'])
        ->name('issues.index');

    Route::get('/issues/{issue}', [IssuesController::class, 'show'])
        ->name('issues.show');

    Route::post('/issues/bulk-status', [IssuesController::class, 'bulkUpdateStatus'])
        ->name('issues.bulk-update-status');

    Route::patch('/issues/{issue}', [IssuesController::class, 'updateStatus'])
        ->name('issues.update-status');

    Route::get('/sources', [SourceStatusController::class, 'index'])
        ->name('sources.index');

    Route::get('/sources/{source}', [SourceStatusController::class, 'show'])
        ->name('sources.show');

    Route::post('/sources/{source}/run-now', [SourceStatusController::class, 'runNow'])
        ->name('sources.run-now');

    Route::get('/sources/{source}/developments/{development}', [SourceStatusController::class, 'showDevelopment'])
        ->name('sources.developments.show')
        ->where('development', '.*');

    Route::get('/sources/{source}/runs/{run}', [SourceStatusController::class, 'showRun'])
        ->name('sources.runs.show')
        ->scopeBindings();

    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');

    Route::put('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');
});
