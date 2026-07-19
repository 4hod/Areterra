<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\Member;
use App\Models\NotificationLog;
use App\Models\User;
use App\Notifications\DailyReminder;
use App\Support\OperatingDays;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class SendRegisterReminder extends Command
{
    protected $signature = 'hub:remind-register';

    protected $description = 'Notify managers if the morning register is not done by 12:00 on an operating day';

    public function handle(): int
    {
        $today = today();

        if (! OperatingDays::isOperatingDay($today)) {
            return self::SUCCESS;
        }

        $scheduled = Member::scheduledFor($today)->count();
        if ($scheduled === 0) {
            return self::SUCCESS;
        }

        $present = Attendance::whereDate('date', $today)->where('checked_in', true)->count();
        if ($present > 0) {
            return self::SUCCESS;
        }

        if (! NotificationLog::claim('register-reminder:'.$today->toDateString())) {
            return self::SUCCESS;
        }

        Notification::send(
            User::managers()->get(),
            new DailyReminder(
                'Morning register not done',
                "{$scheduled} members are scheduled today but nobody has been checked in yet.",
                '/register',
            ),
        );

        $this->info('Register reminder sent.');

        return self::SUCCESS;
    }
}
