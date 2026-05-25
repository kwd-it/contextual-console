<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'daily_summary_enabled' => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();
        $user->daily_summary_enabled = $request->boolean('daily_summary_enabled');
        $user->save();

        return redirect()
            ->route('profile.edit')
            ->with('status', 'Daily summary preferences saved.');
    }
}
