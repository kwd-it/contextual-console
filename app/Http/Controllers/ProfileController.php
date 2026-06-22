<?php

namespace App\Http\Controllers;

use App\Mail\ContextualConsoleDailySummaryMail;
use App\Support\DailyMonitoringSummaryBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class ProfileController extends Controller
{
    private const DAILY_SUMMARY_LOOKBACK_HOURS = 24;

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

    public function updateAccount(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $user->name = $validated['name'];
        $user->save();

        return redirect()
            ->route('profile.edit')
            ->with('account_status', 'Account details saved.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        $user = $request->user();
        $user->password = $request->string('password');
        $user->save();

        return redirect()
            ->route('profile.edit')
            ->with('password_status', 'Password updated.');
    }

    public function sendDailySummaryTestEmail(
        Request $request,
        DailyMonitoringSummaryBuilder $builder,
    ): RedirectResponse {
        $to = trim((string) $request->user()->email);
        if ($to === '') {
            return $this->redirectWithDailySummaryTestFlash(
                'error',
                'Could not send test email: your account has no login email address.',
            );
        }

        try {
            $report = $builder->buildReport(self::DAILY_SUMMARY_LOOKBACK_HOURS);
            Mail::send((new ContextualConsoleDailySummaryMail($report))->to($to));
        } catch (\Throwable) {
            return $this->redirectWithDailySummaryTestFlash(
                'error',
                'Could not send test email. Check mail configuration and try again.',
            );
        }

        return $this->redirectWithDailySummaryTestFlash(
            'success',
            'Test email sent to your login address. Delivery can take a few minutes; check spam if it does not appear.',
        );
    }

    private function redirectWithDailySummaryTestFlash(string $type, string $message): RedirectResponse
    {
        return redirect()
            ->route('profile.edit')
            ->with('daily_summary_test_flash', [
                'type' => $type,
                'message' => $message,
            ]);
    }
}
