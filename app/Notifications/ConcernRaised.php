<?php

namespace App\Notifications;

// Sent to managers when a welfare or end-of-day concern is flagged.
class ConcernRaised extends HubNotification
{
    public function __construct(
        private string $subject,
        private string $detail,
        private string $link = '/',
    ) {
    }

    public function category(): string
    {
        return 'concerns';
    }

    public function title(): string
    {
        return '⚠️ '.$this->subject;
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
