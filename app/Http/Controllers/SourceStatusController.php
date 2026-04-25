<?php

namespace App\Http\Controllers;

use App\Core\Services\MonitoredSourceStatusService;
use Illuminate\View\View;

class SourceStatusController extends Controller
{
    public function index(MonitoredSourceStatusService $status): View
    {
        return view('sources.index', [
            'summaries' => $status->summaries(),
        ]);
    }
}
