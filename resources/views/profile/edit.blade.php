<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Contextual Console - Profile</title>
        @include('sources._dashboard-styles')
    </head>
    <body>
        <div class="cc-page">
            @include('sources._dashboard-nav')

            <header class="cc-page-header">
                <h1 class="cc-page-title"><span>Profile</span></h1>
                <p class="cc-page-sub">Personal account settings for the signed-in user.</p>
            </header>

            <section class="cc-card" aria-labelledby="hdr-account-details" data-test="profile-account-details">
                <div class="cc-card-header">
                    <h2 class="cc-card-title" id="hdr-account-details"><span>Account details</span></h2>
                    <p class="cc-card-desc">Update your display name. Login email is managed by admins.</p>
                </div>
                @if (session('account_status'))
                    <p class="cc-flash cc-flash--notice" role="status" data-test="profile-account-status">{{ session('account_status') }}</p>
                @endif
                <form
                    class="cc-filter-form cc-profile-form"
                    method="post"
                    action="{{ route('profile.update-account') }}"
                    aria-label="Account details"
                    data-test="profile-account-form"
                >
                    @csrf
                    @method('PUT')
                    <div class="cc-profile-form__fields">
                        <label>
                            <span>Name</span>
                            <input
                                type="text"
                                name="name"
                                value="{{ old('name', $user->name) }}"
                                autocomplete="name"
                                required
                                data-test="profile-name-input"
                            >
                            @error('name')
                                <p class="cc-profile-form__error" data-test="profile-name-error">{{ $message }}</p>
                            @enderror
                        </label>
                        <label>
                            <span>Login email</span>
                            <input
                                type="email"
                                value="{{ $user->email }}"
                                readonly
                                data-test="profile-email-readonly"
                            >
                        </label>
                    </div>
                    <div class="cc-filter-form__actions">
                        <button type="submit" data-test="profile-account-save">Save account details</button>
                    </div>
                </form>
            </section>

            <section class="cc-card" aria-labelledby="hdr-password" data-test="profile-password-section">
                <div class="cc-card-header">
                    <h2 class="cc-card-title" id="hdr-password"><span>Password</span></h2>
                    <p class="cc-card-desc">Change your password. New passwords must be at least 12 characters.</p>
                </div>
                @if (session('password_status'))
                    <p class="cc-flash cc-flash--notice" role="status" data-test="profile-password-status">{{ session('password_status') }}</p>
                @endif
                <form
                    class="cc-filter-form cc-profile-form"
                    method="post"
                    action="{{ route('profile.update-password') }}"
                    aria-label="Update password"
                    data-test="profile-password-form"
                >
                    @csrf
                    @method('PUT')
                    <div class="cc-profile-form__fields">
                        <label>
                            <span>Current password</span>
                            <input
                                type="password"
                                name="current_password"
                                autocomplete="current-password"
                                required
                                data-test="profile-current-password-input"
                            >
                            @error('current_password')
                                <p class="cc-profile-form__error" data-test="profile-current-password-error">{{ $message }}</p>
                            @enderror
                        </label>
                        <label>
                            <span>New password</span>
                            <input
                                type="password"
                                name="password"
                                autocomplete="new-password"
                                required
                                data-test="profile-new-password-input"
                            >
                            @error('password')
                                <p class="cc-profile-form__error" data-test="profile-new-password-error">{{ $message }}</p>
                            @enderror
                        </label>
                        <label>
                            <span>Confirm new password</span>
                            <input
                                type="password"
                                name="password_confirmation"
                                autocomplete="new-password"
                                required
                                data-test="profile-password-confirmation-input"
                            >
                        </label>
                    </div>
                    <div class="cc-filter-form__actions">
                        <button type="submit" data-test="profile-password-save">Update password</button>
                    </div>
                </form>
            </section>

            <section class="cc-card" aria-labelledby="hdr-daily-summary">
                <div class="cc-card-header">
                    <h2 class="cc-card-title" id="hdr-daily-summary"><span>Daily summary email</span></h2>
                    <p class="cc-card-desc">When enabled, the daily monitoring summary is sent to your login email address shown above.</p>
                </div>
                @include('_daily-summary-subscription-warning')
                @if (session('status'))
                    <p class="cc-flash cc-flash--notice" role="status" data-test="profile-status">{{ session('status') }}</p>
                @endif
                @if ($dailySummaryTestFlash = session('daily_summary_test_flash'))
                    <p
                        class="cc-flash cc-flash--{{ $dailySummaryTestFlash['type'] }}"
                        role="status"
                        data-test="profile-daily-summary-test-flash"
                        data-test-flash-type="{{ $dailySummaryTestFlash['type'] }}"
                    >{{ $dailySummaryTestFlash['message'] }}</p>
                @endif
                <div class="cc-profile-daily-summary">
                    <form
                        class="cc-profile-daily-summary__column cc-filter-form cc-profile-form"
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
                    <form
                        class="cc-profile-daily-summary__column cc-filter-form cc-profile-form"
                        method="post"
                        action="{{ route('profile.daily-summary-test-email') }}"
                        aria-label="Send test daily summary email"
                        data-test="profile-daily-summary-test-email-form"
                    >
                        @csrf
                        <p class="cc-profile-daily-summary__help">Send the current daily summary to your login email address only.</p>
                        <div class="cc-filter-form__actions">
                            <button type="submit" data-test="profile-daily-summary-test-email" data-profile-test-email-submit>Send test email</button>
                        </div>
                    </form>
                </div>
            </section>
        </div>
        <script>
            document.querySelectorAll('[data-profile-test-email-submit]').forEach(function (button) {
                var form = button.closest('form');
                if (!form) {
                    return;
                }

                form.addEventListener('submit', function () {
                    button.disabled = true;
                    button.setAttribute('aria-busy', 'true');
                    button.textContent = 'Sending...';
                });
            });
        </script>
    </body>
</html>
