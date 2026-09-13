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
        // Dev credentials only — set real passwords before any production deploy.
        $users = [
            ['email' => 'ekilburn@areterra.co.uk', 'name' => 'E Kilburn', 'role' => 'administrator'],
            ['email' => 'lucy@areterra.co.uk', 'name' => 'Lucy Mills', 'role' => 'staff'],
            ['email' => 'vanessa@areterra.co.uk', 'name' => 'Vanessa Goodall', 'role' => 'staff'],
        ];

        foreach ($users as $attributes) {
            $user = User::firstOrCreate(
                ['email' => $attributes['email']],
                [...$attributes, 'password' => 'password'],
            );

            // Model events are deliberately disabled while seeding, so new
            // users do not receive the grants normally applied in User::booted().
            // Repair only empty grant sets to preserve any customised access.
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
