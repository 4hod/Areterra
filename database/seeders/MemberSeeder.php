<?php

namespace Database\Seeders;

use App\Models\Member;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MemberSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $members = [
            ['Amy', 'Buckle', null],
            ['Andrew', 'Toomey', null],
            ['Colin', 'Gibbons', null],
            ['Coriander', 'Montgomery', null],
            ['Elizabeth', 'Russell', null],
            ['Gareth', 'Warner', 'Gaz'],
            ['Kim', 'Scriven', null],
            ['Matthew', 'England', 'Matt'],
            ['Michelle', 'Ballard', null],
            ['Michelle', 'Walker', null],
            ['Nicholas', 'Thomas', 'Nick'],
            ['William', 'Garrett', 'Billy'],
        ];

        foreach ($members as [$first, $last, $preferred]) {
            $member = Member::firstOrCreate(
                ['first_name' => $first, 'last_name' => $last],
                ['preferred_name' => $preferred, 'status' => 'active'],
            );

            // Operating days Mon/Tue/Thu/Fri; adjust per member in the Hub.
            $member->settings()->firstOrCreate([], [
                'transport_required' => false,
                'attendance_days' => [1, 2, 4, 5],
            ]);
        }
    }
}
