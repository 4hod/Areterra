<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        User::query()
            ->whereIn('role', ['administrator', 'manager'])
            ->each(fn (User $user) => $user->capabilityGrants()->firstOrCreate([
                'capability' => 'manage_member_communications',
            ]));
    }

    public function down(): void
    {
        \App\Models\UserCapability::query()
            ->where('capability', 'manage_member_communications')
            ->delete();
    }
};
