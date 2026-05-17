<?php

namespace App\Http\Controllers;

use App\Support\DashboardViewData;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(DashboardViewData $dashboardViewData): View
    {
        return view('dashboard.index', $dashboardViewData->forIndex());
    }
}
