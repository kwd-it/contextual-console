<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
        $isSelf = $request->user()?->is($user);

        $rules = [
            'role' => ['required', 'in:'.User::ROLE_ADMIN.','.User::ROLE_OPERATOR],
            'daily_summary_enabled' => ['sometimes', 'boolean'],
        ];

        if (! $isSelf) {
            $rules['email'] = [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ];
        }

        $validated = $request->validate($rules);

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

        $user->role = $validated['role'];
        $user->daily_summary_enabled = $request->boolean('daily_summary_enabled');

        if (! $isSelf) {
            $user->email = $validated['email'];
        }

        $user->save();

        return redirect()
            ->route('admin.users.index')
            ->with('status', "Saved changes for {$user->name}.");
    }
}
