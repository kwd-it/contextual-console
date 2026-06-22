<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class PromoteUserToAdminCommand extends Command
{
    protected $signature = 'contextual-console:promote-user-to-admin {--email=}';

    protected $description = 'Promote an existing user to the admin role (safe to re-run).';

    public function handle(): int
    {
        $email = (string) ($this->option('email') ?? '');

        $validator = Validator::make(
            ['email' => $email],
            ['email' => ['required', 'email']],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error("No user found with email {$email}.");

            return self::FAILURE;
        }

        if ($user->isAdmin()) {
            $this->info("User {$email} is already an admin.");

            return self::SUCCESS;
        }

        $user->role = User::ROLE_ADMIN;
        $user->save();

        $this->info("User {$email} promoted to admin.");

        return self::SUCCESS;
    }
}
