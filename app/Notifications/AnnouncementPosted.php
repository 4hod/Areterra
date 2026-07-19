<?php

namespace App\Notifications;

use App\Models\Announcement;

class AnnouncementPosted extends HubNotification
{
    public function __construct(private Announcement $announcement)
    {
    }

    public function category(): string
    {
        return 'announcements';
    }

    public function title(): string
    {
        return '📢 '.$this->announcement->title;
    }

    public function body(): string
    {
        return str($this->announcement->body)->limit(140)->toString();
    }

    public function url(): string
    {
        return '/announcements';
    }
}
