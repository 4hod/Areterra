<?php

namespace App\Notifications;

class AutomationAlert extends HubNotification
{
    public function __construct(private string $heading, private string $message, private string $link = '/tasks') {}

    public function category(): string { return 'operations'; }
    public function title(): string { return $this->heading; }
    public function body(): string { return $this->message; }
    public function url(): string { return $this->link; }
}
