<?php

namespace App\Console\Commands;

use App\Http\Controllers\MicrosoftAuthController;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class SecurityCheck extends Command
{
    protected $signature = 'hub:security-check';

    protected $description = 'Fail when known rollout security requirements are not satisfied';

    public function handle(): int
    {
        $failures = [];

        if (! app()->environment('production')) {
            $this->warn('This check is most useful with the production environment/configuration.');
        }
        if (config('app.debug')) {
            $failures[] = 'APP_DEBUG must be false.';
        }
        if (! config('session.encrypt')) {
            $failures[] = 'SESSION_ENCRYPT must be true.';
        }
        if (! config('session.secure')) {
            $failures[] = 'SESSION_SECURE_COOKIE must be true.';
        }
        if (! MicrosoftAuthController::configured()) {
            $failures[] = 'Tenant-specific Microsoft sign-in is incomplete.';
        }
        if (! config('security.require_microsoft_sso')) {
            $failures[] = 'REQUIRE_MICROSOFT_SSO must be true after Entra MFA testing.';
        }

        $weakPasswords = ['password', 'Password123', 'Password123!', 'Areterra123', 'Areterra123!'];
        $weakAccounts = User::withTrashed()->get()->filter(
            fn (User $user) => collect($weakPasswords)->contains(fn (string $password) => Hash::check($password, $user->password)),
        );
        if ($weakAccounts->isNotEmpty()) {
            $failures[] = $weakAccounts->count().' account(s) still use a known deployment password.';
        }

        $publicMedia = collect(['member-photos', 'staff-photos', 'end-of-day-photos'])
            ->flatMap(fn (string $directory) => Storage::disk('public')->allFiles($directory));
        if ($publicMedia->isNotEmpty()) {
            $failures[] = $publicMedia->count().' protected media file(s) remain on the public disk.';
        }

        foreach ($failures as $failure) {
            $this->error($failure);
        }

        if ($failures !== []) {
            return self::FAILURE;
        }

        $this->info('Security rollout checks passed. Independent CI, staging and penetration testing are still required.');

        return self::SUCCESS;
    }
}
