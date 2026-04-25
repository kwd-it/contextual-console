<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateAdminUserCommand extends Command
{
    protected $signature = 'contextual-console:create-admin-user {--name=} {--email=} {--password=}';

    protected $description = 'Create an admin user for Contextual Console (manual provisioning).';

    public function handle(): int
    {
        $name = (string) ($this->option('name') ?? '');
        $email = (string) ($this->option('email') ?? '');
        $password = (string) ($this->option('password') ?? '');

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            [
                'name' => ['required', 'string'],
                'email' => ['required', 'email'],
                'password' => ['required', 'string', 'min:12'],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        if (User::query()->where('email', $email)->exists()) {
            $this->error('A user with this email already exists.');

            return self::FAILURE;
        }

        User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
        ]);

        $this->info("Admin user created: {$email}");

        return self::SUCCESS;
    }
}
