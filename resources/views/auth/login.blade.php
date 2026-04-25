<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Contextual Console</title>
        <style>
            body { font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji", "Segoe UI Emoji"; margin: 24px; color: #111827; }
            h1 { margin: 0 0 16px; font-size: 22px; }
            label { display: block; font-weight: 600; color: #374151; font-size: 13px; margin: 0 0 6px; }
            input[type="email"], input[type="password"] { width: 100%; max-width: 420px; padding: 10px 10px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 14px; }
            .row { margin: 0 0 14px; }
            .muted { color: #6b7280; font-size: 13px; }
            .error { color: #b91c1c; font-size: 13px; margin-top: 6px; }
            button { padding: 10px 14px; border: 1px solid #e5e7eb; border-radius: 6px; background: #111827; color: #fff; font-size: 14px; cursor: pointer; }
            button:hover { background: #0b1220; }
            .checkbox { display: flex; align-items: center; gap: 8px; }
        </style>
    </head>
    <body>
        <h1>Sign in</h1>

        <form method="post" action="{{ route('login.store') }}">
            @csrf

            <div class="row">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required>
                @error('email')
                    <div class="error">{{ $message }}</div>
                @enderror
            </div>

            <div class="row">
                <label for="password">Password</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required>
                @error('password')
                    <div class="error">{{ $message }}</div>
                @enderror
            </div>

            <div class="row">
                <label class="checkbox">
                    <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                    <span class="muted">Remember me</span>
                </label>
            </div>

            <button type="submit">Sign in</button>
        </form>
    </body>
</html>
