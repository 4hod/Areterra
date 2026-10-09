<?php

use App\Support\LeaveCalendar;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // The old blanket 28-day value included bank holidays even though the
        // calculation now excludes them. Correct only untouched defaults;
        // bespoke allowances are preserved.
        DB::table('leave_balances')->where('entitlement_days', 28)->orderBy('id')->eachById(function ($balance) {
            $workingDays = DB::table('users')->where('id', $balance->user_id)->value('working_days');
            $workingDays = is_string($workingDays) ? json_decode($workingDays, true) : $workingDays;
            $workingDays = is_array($workingDays) && $workingDays !== [] ? $workingDays : LeaveCalendar::DEFAULT_WORKING_DAYS;

            DB::table('leave_balances')->where('id', $balance->id)->update([
                'entitlement_days' => round(20 * (count(array_unique($workingDays)) / 5), 1),
            ]);
        });
    }

    public function down(): void
    {
        // Corrected or manager-edited HR figures must not be guessed backwards.
    }
};
