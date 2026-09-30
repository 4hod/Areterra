<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules\Password;

class ResetUserPassword extends Command
{
    protected $signature = 'hub:reset-user-password {email}';

    protected $description = 'Securely reset a Hub user password without placing it in shell history';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $user = User::withTrashed()->whereRaw('lower(email) = ?', [$email])->first();
        if (! $user) {
            $this->error('No account was found for that email address.');

            return self::FAILURE;
        }

        $password = (string) $this->secret('New password (12–128 characters, including letters and numbers)');
        $confirmation = (string) $this->secret('Confirm new password');
        $validator = Validator::make([
            'password' => $password,
            'password_confirmation' => $confirmation,
        ], [
            'password' => ['required', 'string', 'max:128', 'confirmed', Password::min(12)->letters()->numbers()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user->forceFill([
            'password' => $password,
            'remember_token' => null,
        ])->save();

        $sessionTable = (string) config('session.table', 'sessions');
        if (config('session.driver') === 'database' && Schema::hasTable($sessionTable)) {
            DB::table($sessionTable)->where('user_id', $user->id)->delete();
        }

        $this->info("Password reset for {$user->email}. Existing remembered sessions have been invalidated.");

        return self::SUCCESS;
    }
}
