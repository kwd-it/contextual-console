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
                <p class="cc-page-sub">Manage user accounts, roles, and daily summary email subscriptions.</p>
            </header>

            <section class="cc-card" aria-labelledby="hdr-create-user" data-test="admin-users-create-section">
                <div class="cc-card-header">
                    <h2 class="cc-card-title" id="hdr-create-user"><span>Create user</span></h2>
                    <p class="cc-card-desc">Add a new account. Login email cannot be changed after creation.</p>
                </div>
                <form
                    class="cc-filter-form cc-profile-form"
                    method="post"
                    action="{{ route('admin.users.store') }}"
                    aria-label="Create user"
                    data-test="admin-user-create-form"
                >
                    @csrf
                    <div class="cc-profile-form__fields">
                        <label>
                            <span>Name</span>
                            <input
                                type="text"
                                name="name"
                                value="{{ old('name') }}"
                                autocomplete="name"
                                required
                                data-test="admin-user-create-name-input"
                            >
                            @error('name')
                                <p class="cc-profile-form__error" data-test="admin-user-create-name-error">{{ $message }}</p>
                            @enderror
                        </label>
                        <label>
                            <span>Login email</span>
                            <input
                                type="email"
                                name="email"
                                value="{{ old('email') }}"
                                autocomplete="email"
                                required
                                data-test="admin-user-create-email-input"
                            >
                            @error('email')
                                <p class="cc-profile-form__error" data-test="admin-user-create-email-error">{{ $message }}</p>
                            @enderror
                        </label>
                        <label>
                            <span>Role</span>
                            <select name="role" data-test="admin-user-create-role">
                                @foreach (\App\Models\User::roleLabels() as $value => $label)
                                    <option value="{{ $value }}" @selected(old('role', \App\Models\User::ROLE_OPERATOR) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('role')
                                <p class="cc-profile-form__error" data-test="admin-user-create-role-error">{{ $message }}</p>
                            @enderror
                        </label>
                        <label>
                            <span>Initial password</span>
                            <input
                                type="password"
                                name="password"
                                autocomplete="new-password"
                                required
                                data-test="admin-user-create-password-input"
                            >
                            <span class="cc-field-hint">Contextual Console does not send invite emails or offer self-service password reset. Set an initial password here and share it securely with the new user.</span>
                            @error('password')
                                <p class="cc-profile-form__error" data-test="admin-user-create-password-error">{{ $message }}</p>
                            @enderror
                        </label>
                        <label class="cc-profile-form__checkbox">
                            <input
                                type="checkbox"
                                name="daily_summary_enabled"
                                value="1"
                                data-test="admin-user-create-daily-summary-enabled"
                                @checked(old('daily_summary_enabled'))
                            >
                            <span>Daily summary subscription</span>
                        </label>
                    </div>
                    <div class="cc-filter-form__actions">
                        <button type="submit" data-test="admin-user-create-submit">Create user</button>
                    </div>
                </form>
            </section>

            <section class="cc-card" aria-labelledby="hdr-user-list" data-test="admin-users-page">
                <div class="cc-card-header">
                    <h2 class="cc-card-title" id="hdr-user-list"><span>User list</span></h2>
                    <p class="cc-card-desc">Admins can change names, roles, and daily summary subscriptions. Login email is set when a user is created and cannot be changed here. Last sign-in times use the app display timezone.</p>
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
                                @php
                                    $formId = 'admin-user-form-'.$user->id;
                                    $passwordFormId = 'admin-user-password-form-'.$user->id;
                                    $deleteFormId = 'admin-user-delete-form-'.$user->id;
                                    $isSelf = auth()->id() === $user->id;
                                    $canDelete = ! $isSelf && ! ($user->isAdmin() && $adminCount <= 1);
                                    $canResetPassword = ! $isSelf;
                                @endphp
                                <tr data-test="admin-user-row" data-user-id="{{ $user->id }}">
                                    <td>
                                        <label>
                                            <span class="muted">Name</span>
                                            <input
                                                type="text"
                                                name="name"
                                                form="{{ $formId }}"
                                                value="{{ $user->name }}"
                                                class="cc-admin-user-name-input"
                                                data-test="admin-user-name-input"
                                                required
                                            >
                                        </label>
                                    </td>
                                    <td>
                                        <span class="mono" data-test="admin-user-email-readonly">{{ $user->email }}</span>
                                    </td>
                                    <td>
                                        @if ($isSelf)
                                            <span data-test="admin-user-role-readonly">{{ \App\Models\User::roleLabels()[$user->role] ?? $user->role }}</span>
                                        @else
                                            <label>
                                                <span class="muted">Role</span>
                                                <select name="role" form="{{ $formId }}" data-test="admin-user-role">
                                                    @foreach (\App\Models\User::roleLabels() as $value => $label)
                                                        <option value="{{ $value }}" @selected($user->role === $value)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </label>
                                        @endif
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
                                        <div class="cc-admin-user-actions">
                                            <div class="cc-admin-user-actions__primary">
                                                <button type="submit" form="{{ $formId }}" class="cc-btn-save" data-test="admin-user-save">Save</button>
                                                @if ($canResetPassword)
                                                    <details class="cc-admin-user-reset" data-test="admin-user-reset-details">
                                                        <summary data-test="admin-user-reset-password">Reset password</summary>
                                                        <form
                                                            id="{{ $passwordFormId }}"
                                                            class="cc-admin-user-password-reset"
                                                            method="post"
                                                            action="{{ route('admin.users.reset-password', $user) }}"
                                                            data-test="admin-user-password-form"
                                                        >
                                                            @csrf
                                                            @method('PUT')
                                                            <label>
                                                                <span class="muted">New password</span>
                                                                <input
                                                                    type="password"
                                                                    name="password"
                                                                    autocomplete="new-password"
                                                                    required
                                                                    data-test="admin-user-reset-password-input"
                                                                >
                                                            </label>
                                                            <button type="submit" data-test="admin-user-reset-password-submit">Update password</button>
                                                        </form>
                                                    </details>
                                                @endif
                                                @if ($canDelete)
                                                    <form
                                                        id="{{ $deleteFormId }}"
                                                        class="cc-admin-user-delete"
                                                        method="post"
                                                        action="{{ route('admin.users.destroy', $user) }}"
                                                        data-test="admin-user-delete-form"
                                                        onsubmit="return confirm('Delete {{ $user->name }}? This cannot be undone.');"
                                                    >
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="cc-btn-danger-outline" data-test="admin-user-delete">Delete</button>
                                                    </form>
                                                @endif
                                            </div>
                                        </div>
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
