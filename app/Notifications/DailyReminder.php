<?php

namespace App\Notifications;

class DailyReminder extends HubNotification
{
    public function __construct(
        private string $heading,
        private string $detail,
        private string $link,
    ) {
    }

    public function category(): string
    {
        return 'reminders';
    }

    public function title(): string
    {
        return '⏰ '.$this->heading;
    }

    public function body(): string
    {
        return $this->detail;
    }

    public function url(): string
    {
        return $this->link;
    }
}
