<?php

use App\Console\Commands\BackupDatabaseCommand;
use App\Console\Commands\CreateAdminUserCommand;
use App\Console\Commands\DailySummaryCommand;
use App\Console\Commands\ProductionSmokeTestCommand;
use App\Console\Commands\RunHttpPlotSourceCommand;
use App\Console\Commands\RunPlotSourceCommand;
use App\Console\Commands\RunScheduledSourcesCommand;
use App\Console\Commands\SourceStatusCommand;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withCommands([
        BackupDatabaseCommand::class,
        CreateAdminUserCommand::class,
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
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
