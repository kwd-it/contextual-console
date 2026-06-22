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
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'role' => ['required', 'in:'.User::ROLE_ADMIN.','.User::ROLE_OPERATOR],
            'daily_summary_enabled' => ['sometimes', 'boolean'],
        ]);

        $isDemotion = $validated['role'] === User::ROLE_OPERATOR && $user->isAdmin();

        if ($isDemotion) {
            $adminCount = User::query()->where('role', User::ROLE_ADMIN)->count();

            if ($adminCount <= 1) {
                return redirect()
                    ->route('admin.users.index')
                    ->with('error', 'At least one admin is required. Promote another user to admin first.');
            }

            if ($request->user()?->is($user)) {
                return redirect()
                    ->route('admin.users.index')
                    ->with('error', 'You cannot change your own role from admin to operator.');
            }
        }

        $user->name = $validated['name'];
        $user->role = $validated['role'];
        $user->daily_summary_enabled = $request->boolean('daily_summary_enabled');

        $user->save();

        return redirect()
            ->route('admin.users.index')
            ->with('status', "Saved changes for {$user->name}.");
    }
}
