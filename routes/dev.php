<?php

use App\Http\Controllers\Dev\DailySummaryEmailPreviewController;
use Illuminate\Support\Facades\Route;

Route::get('/dev/daily-summary-email-preview', DailySummaryEmailPreviewController::class)
    ->name('dev.daily-summary-email-preview');
