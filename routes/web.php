<?php

use App\Http\Controllers\Admin\UsersController;
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

    Route::put('/profile/account', [ProfileController::class, 'updateAccount'])
        ->name('profile.update-account');

    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])
        ->name('profile.update-password');

    Route::post('/profile/daily-summary-test-email', [ProfileController::class, 'sendDailySummaryTestEmail'])
        ->name('profile.daily-summary-test-email');

    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', [UsersController::class, 'index'])
            ->name('users.index');

        Route::post('/users', [UsersController::class, 'store'])
            ->name('users.store');

        Route::put('/users/{user}', [UsersController::class, 'update'])
            ->name('users.update');

        Route::put('/users/{user}/password', [UsersController::class, 'resetPassword'])
            ->name('users.reset-password');

        Route::delete('/users/{user}', [UsersController::class, 'destroy'])
            ->name('users.destroy');
    });
});
