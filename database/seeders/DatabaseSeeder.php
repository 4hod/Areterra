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
        User::firstOrCreate(['email' => 'ekilburn@areterra.co.uk'], [
            'name' => 'E Kilburn',
            'password' => 'password',
            'role' => 'administrator',
        ]);

        User::firstOrCreate(['email' => 'lucy@areterra.co.uk'], [
            'name' => 'Lucy Mills',
            'password' => 'password',
            'role' => 'staff',
        ]);

        User::firstOrCreate(['email' => 'vanessa@areterra.co.uk'], [
            'name' => 'Vanessa Goodall',
            'password' => 'password',
            'role' => 'staff',
        ]);

        $this->call([
            MemberSeeder::class,
            AnimalSeeder::class,
        ]);
    }
}
