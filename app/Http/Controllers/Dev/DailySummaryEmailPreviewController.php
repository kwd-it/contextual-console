<?php

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use App\Support\DailyMonitoringSummaryBuilder;
use Illuminate\View\View;

class DailySummaryEmailPreviewController extends Controller
{
    public function __invoke(DailyMonitoringSummaryBuilder $builder): View
    {
        $report = $builder->buildReport(24);

        return view('emails.contextual-console.daily-summary-html', [
            'report' => $report,
        ]);
    }
}
