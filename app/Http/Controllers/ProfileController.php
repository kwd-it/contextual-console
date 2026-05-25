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
        $enabled = $request->boolean('daily_summary_enabled');

        $emailRules = ['nullable', 'max:255'];
        if ($enabled) {
            $emailRules = ['required', 'email', 'max:255'];
        }

        $validated = $request->validate([
            'daily_summary_enabled' => ['sometimes', 'boolean'],
            'daily_summary_email' => $emailRules,
        ]);

        $email = $validated['daily_summary_email'] ?? null;
        if (is_string($email)) {
            $email = trim($email);
            if ($email === '') {
                $email = null;
            }
        }

        $user = $request->user();
        $user->daily_summary_enabled = $enabled;
        $user->daily_summary_email = $enabled ? $email : null;
        $user->save();

        return redirect()
            ->route('profile.edit')
            ->with('status', 'Email report preferences saved.');
    }
}
