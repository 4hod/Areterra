<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\EndOfDayRecord;
use App\Models\NotificationLog;
use App\Models\User;
use App\Notifications\DailyReminder;
use App\Support\OperatingDays;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class SendEndOfDayReminder extends Command
{
    protected $signature = 'hub:remind-end-of-day';

    protected $description = 'Notify managers if end-of-day records are missing by 14:30 on an operating day';

    public function handle(): int
    {
        $today = today();

        if (! OperatingDays::isOperatingDay($today)) {
            return self::SUCCESS;
        }

        $attendees = Attendance::whereDate('date', $today)->where('checked_in', true)->pluck('member_id');
        if ($attendees->isEmpty()) {
            return self::SUCCESS;
        }

        $recorded = EndOfDayRecord::whereDate('date', $today)->whereIn('member_id', $attendees)->count();
        $missing = $attendees->count() - $recorded;
        if ($missing <= 0) {
            return self::SUCCESS;
        }

        if (! NotificationLog::claim('end-of-day-reminder:'.$today->toDateString())) {
            return self::SUCCESS;
        }

        Notification::send(
            User::managers()->get(),
            new DailyReminder(
                'End of day records missing',
                "{$missing} of {$attendees->count()} members who attended today have no end-of-day record yet.",
                '/end-of-day',
            ),
        );

        $this->info('End of day reminder sent.');

        return self::SUCCESS;
    }
}
