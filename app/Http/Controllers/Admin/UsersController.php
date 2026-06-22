<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UsersController extends Controller
{
    public function index(): View
    {
        return view('admin.users.index', [
            'users' => User::query()->orderBy('name')->orderBy('email')->get(),
            'adminCount' => User::query()->where('role', User::ROLE_ADMIN)->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', 'in:'.User::ROLE_ADMIN.','.User::ROLE_OPERATOR],
            'password' => ['required', 'string', 'min:12'],
            'daily_summary_enabled' => ['sometimes', 'boolean'],
        ]);

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'password' => $validated['password'],
            'daily_summary_enabled' => $request->boolean('daily_summary_enabled'),
        ]);

        return redirect()
            ->route('admin.users.index')
            ->with('status', "Created user {$user->name}.");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $isSelfUpdate = $request->user()?->is($user) ?? false;

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'daily_summary_enabled' => ['sometimes', 'boolean'],
        ];

        if ($isSelfUpdate) {
            $rules['role'] = ['sometimes', 'in:'.User::ROLE_ADMIN.','.User::ROLE_OPERATOR];
        } else {
            $rules['role'] = ['required', 'in:'.User::ROLE_ADMIN.','.User::ROLE_OPERATOR];
        }

        $validated = $request->validate($rules);

        $role = $isSelfUpdate ? $user->role : $validated['role'];

        $isDemotion = $role === User::ROLE_OPERATOR && $user->isAdmin();

        if ($isDemotion) {
            $adminCount = User::query()->where('role', User::ROLE_ADMIN)->count();

            if ($adminCount <= 1) {
                return redirect()
                    ->route('admin.users.index')
                    ->with('error', 'At least one admin is required. Promote another user to admin first.');
            }
        }

        $user->name = $validated['name'];
        $user->role = $role;
        $user->daily_summary_enabled = $request->boolean('daily_summary_enabled');

        $user->save();

        return redirect()
            ->route('admin.users.index')
            ->with('status', "Saved changes for {$user->name}.");
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        if ($request->user()?->is($user)) {
            return redirect()
                ->route('admin.users.index')
                ->with('error', 'Use Profile to change your own password.');
        }

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:12'],
        ]);

        $user->password = $validated['password'];
        $user->save();

        return redirect()
            ->route('admin.users.index')
            ->with('status', "Reset password for {$user->name}.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($request->user()?->is($user)) {
            return redirect()
                ->route('admin.users.index')
                ->with('error', 'You cannot delete your own account.');
        }

        if ($user->isAdmin() && User::query()->where('role', User::ROLE_ADMIN)->count() <= 1) {
            return redirect()
                ->route('admin.users.index')
                ->with('error', 'At least one admin is required. Promote another user to admin first.');
        }

        $name = $user->name;
        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('status', "Deleted user {$name}.");
    }
}
