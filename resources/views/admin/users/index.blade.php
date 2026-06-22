<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Contextual Console - Users</title>
        @include('sources._dashboard-styles')
    </head>
    <body>
        <div class="cc-page">
            @include('sources._dashboard-nav')

            <header class="cc-page-header">
                <h1 class="cc-page-title"><span>Users</span></h1>
                <p class="cc-page-sub">Manage user roles and daily summary email subscriptions.</p>
            </header>

            <section class="cc-card" aria-labelledby="hdr-user-list" data-test="admin-users-page">
                <div class="cc-card-header">
                    <h2 class="cc-card-title" id="hdr-user-list"><span>User list</span></h2>
                    <p class="cc-card-desc">Admins can change roles, email addresses, and daily summary subscriptions. Last sign-in times use the app display timezone.</p>
                </div>
                @if (session('status'))
                    <p class="cc-flash cc-flash--notice" role="status" data-test="admin-users-status">{{ session('status') }}</p>
                @endif
                @if (session('error'))
                    <p class="cc-flash cc-flash--error" role="alert" data-test="admin-users-error">{{ session('error') }}</p>
                @endif
                <div class="cc-card-body">
                    <table class="cc-table" data-test="admin-users-table">
                        <thead>
                            <tr>
                                <th scope="col">Name</th>
                                <th scope="col">Email</th>
                                <th scope="col">Role</th>
                                <th scope="col">Daily summary</th>
                                <th scope="col">Last sign-in</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($users as $user)
                                @php($formId = 'admin-user-form-'.$user->id)
                                <tr data-test="admin-user-row" data-user-id="{{ $user->id }}">
                                    <td data-test="admin-user-name">{{ $user->name }}</td>
                                    <td>
                                        @if ($user->is(auth()->user()))
                                            <span class="mono" data-test="admin-user-email-readonly">{{ $user->email }}</span>
                                        @else
                                            <label>
                                                <span class="muted">Email</span>
                                                <input
                                                    type="email"
                                                    name="email"
                                                    form="{{ $formId }}"
                                                    value="{{ $user->email }}"
                                                    class="mono cc-admin-user-email-input"
                                                    data-test="admin-user-email-input"
                                                    required
                                                >
                                            </label>
                                        @endif
                                    </td>
                                    <td>
                                        <label>
                                            <span class="muted">Role</span>
                                            <select name="role" form="{{ $formId }}" data-test="admin-user-role">
                                                @foreach (\App\Models\User::roleLabels() as $value => $label)
                                                    <option value="{{ $value }}" @selected($user->role === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                    </td>
                                    <td>
                                        <label class="cc-profile-form__checkbox">
                                            <input
                                                type="checkbox"
                                                name="daily_summary_enabled"
                                                value="1"
                                                form="{{ $formId }}"
                                                data-test="admin-user-daily-summary-enabled"
                                                @checked($user->daily_summary_enabled)
                                            >
                                            <span>Subscribed</span>
                                        </label>
                                        @if ($user->daily_summary_enabled)
                                            <span class="cc-badge cc-badge--ok" data-test="admin-user-daily-summary-badge">Subscribed</span>
                                        @else
                                            <span class="cc-badge cc-badge--neutral" data-test="admin-user-daily-summary-badge">Not subscribed</span>
                                        @endif
                                    </td>
                                    <td class="mono cc-time" data-test="admin-user-last-sign-in">
                                        {{ \App\Support\DisplayTimestamp::format($user->last_signed_in_at) }}
                                    </td>
                                    <td>
                                        <button type="submit" form="{{ $formId }}" data-test="admin-user-save">Save</button>
                                        <form
                                            id="{{ $formId }}"
                                            method="post"
                                            action="{{ route('admin.users.update', $user) }}"
                                            data-test="admin-user-form"
                                            hidden
                                        >
                                            @csrf
                                            @method('PUT')
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </body>
</html>
