<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::before(fn (User $user) => $user->isAdministrator() ? true : null);

        foreach (config('capabilities.roles') as $capabilities) {
            foreach ($capabilities as $capability) {
                Gate::define($capability, fn (User $user) => $user->hasCapability($capability));
            }
        }
    }
}
