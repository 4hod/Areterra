<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('Development seed data was not installed in production. Create named user accounts through an approved setup process.');

            return;
        }

        // Dev credentials only — set real passwords before any production deploy.
        $administrator = User::firstOrCreate(['email' => 'ekilburn@areterra.co.uk'], [
            'name' => 'E Kilburn',
            'password' => 'password',
            'role' => 'administrator',
        ]);

        $lucy = User::firstOrCreate(['email' => 'lucy@areterra.co.uk'], [
            'name' => 'Lucy Mills',
            'password' => 'password',
            'role' => 'staff',
        ]);

        $vanessa = User::firstOrCreate(['email' => 'vanessa@areterra.co.uk'], [
            'name' => 'Vanessa Goodall',
            'password' => 'password',
            'role' => 'staff',
        ]);

        // Model events are intentionally disabled while seeding, so apply the
        // normal new-account presets explicitly instead of creating accounts
        // that can authenticate but have no permissions.
        foreach ([$administrator, $lucy, $vanessa] as $user) {
            if ($user->capabilityGrants()->doesntExist()) {
                $user->syncCapabilities(User::preset($user->role));
            }
        }

        foreach ([['Lucy Mills', 12.71], ['Vanessa Goodall', 13.36]] as [$name, $rate]) {
            $member = \App\Models\StaffRosterMember::firstOrCreate(['name' => $name], ['active' => true]);
            if ($member->rates()->doesntExist()) {
                $member->rates()->create(['hourly_rate' => $rate, 'effective_from' => today()]);
            }
        }

        \App\Models\Vehicle::firstOrCreate(
            ['registration' => 'YE66 EGY'],
            ['make_model' => 'Silver Ford Transit Custom', 'active' => true],
        );

        $this->call([
            MemberSeeder::class,
            AnimalSeeder::class,
        ]);
    }
}
