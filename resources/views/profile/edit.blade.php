<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Contextual Console - Email reports</title>
        @include('sources._dashboard-styles')
    </head>
    <body>
        <div class="cc-page">
            @include('sources._dashboard-nav')

            <header class="cc-page-header">
                <h1 class="cc-page-title"><span>Email reports</span></h1>
                <p class="cc-page-sub">Choose whether to receive the daily monitoring summary email and which address to send it to.</p>
            </header>

            <section class="cc-card" aria-labelledby="hdr-email-reports">
                <div class="cc-card-header">
                    <h2 class="cc-card-title" id="hdr-email-reports"><span>Daily summary email</span></h2>
                    <p class="cc-card-desc">When enabled, the scheduled daily summary is sent to the address below instead of the shared fallback recipient.</p>
                </div>
                @if (session('status'))
                    <p class="cc-flash" role="status" data-test="profile-status">{{ session('status') }}</p>
                @endif
                <form
                    class="cc-filter-form cc-profile-form"
                    method="post"
                    action="{{ route('profile.update') }}"
                    aria-label="Daily summary email preferences"
                    data-test="profile-form"
                >
                    @csrf
                    @method('PUT')
                    <div class="cc-profile-form__fields">
                        <label class="cc-profile-form__checkbox">
                            <input
                                type="checkbox"
                                name="daily_summary_enabled"
                                value="1"
                                data-test="daily-summary-enabled"
                                @checked(old('daily_summary_enabled', $user->daily_summary_enabled))
                            >
                            <span>Send me the daily monitoring summary email</span>
                        </label>
                        <label>
                            Summary email address
                            <input
                                type="email"
                                name="daily_summary_email"
                                value="{{ old('daily_summary_email', $user->daily_summary_email) }}"
                                autocomplete="email"
                                data-test="daily-summary-email"
                            >
                        </label>
                        @error('daily_summary_email')
                            <p class="cc-profile-form__error" data-test="daily-summary-email-error">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="cc-filter-form__actions">
                        <button type="submit" data-test="profile-save">Save preferences</button>
                        @if (Route::has('dev.daily-summary-email-preview'))
                            <a
                                href="{{ route('dev.daily-summary-email-preview') }}"
                                class="cc-filter-form__clear"
                                target="_blank"
                                rel="noopener noreferrer"
                                data-test="profile-preview-daily-summary"
                            >Preview daily summary email</a>
                        @endif
                    </div>
                </form>
            </section>
        </div>
    </body>
</html>
