<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdministrator extends Command
{
    protected $signature = 'hub:create-administrator';

    protected $description = 'Interactively create the first named Hub administrator without exposing a password in shell history';

    public function handle(): int
    {
        $name = trim((string) $this->ask('Full name'));
        $email = mb_strtolower(trim((string) $this->ask('Email address')));
        $password = (string) $this->secret('Password (12+ characters, including letters and numbers)');
        $confirmation = (string) $this->secret('Confirm password');

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $confirmation,
        ], [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'max:128', 'confirmed', Password::min(12)->letters()->numbers()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => 'administrator',
        ]);

        $this->info("Administrator {$user->email} created. Configure Microsoft Entra MFA before inviting staff.");

        return self::SUCCESS;
    }
}
