<?php

use App\Http\Controllers\SourceStatusController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/sources', [SourceStatusController::class, 'index'])
    ->name('sources.index');

Route::get('/sources/{source}', [SourceStatusController::class, 'show'])
    ->name('sources.show');
