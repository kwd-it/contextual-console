<?php

use App\Http\Middleware\RestrictToLocalOrDevelopmentEnvironment;
use App\Console\Commands\BackupDatabaseCommand;
use App\Console\Commands\CreateAdminUserCommand;
use App\Console\Commands\PromoteUserToAdminCommand;
use App\Console\Commands\DailySummaryCommand;
use App\Console\Commands\ProductionSmokeTestCommand;
use App\Console\Commands\RunHttpPlotSourceCommand;
use App\Console\Commands\RunPlotSourceCommand;
use App\Console\Commands\RunScheduledSourcesCommand;
use App\Console\Commands\SourceStatusCommand;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withCommands([
        BackupDatabaseCommand::class,
        CreateAdminUserCommand::class,
        PromoteUserToAdminCommand::class,
        DailySummaryCommand::class,
        ProductionSmokeTestCommand::class,
        RunPlotSourceCommand::class,
        RunHttpPlotSourceCommand::class,
        RunScheduledSourcesCommand::class,
        SourceStatusCommand::class,
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            if (! app()->environment('production')) {
                Route::middleware([
                    'web',
                    'auth',
                    RestrictToLocalOrDevelopmentEnvironment::class,
                ])->group(base_path('routes/dev.php'));
            }
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
