<?php

namespace App\Notifications;

use App\Models\LeaveRequest;

class LeaveSubmitted extends HubNotification
{
    public function __construct(private LeaveRequest $request)
    {
    }

    public function category(): string
    {
        return 'leave';
    }

    public function title(): string
    {
        return '🌴 Leave request from '.$this->request->user->name;
    }

    public function body(): string
    {
        return ucfirst($this->request->type).' leave, '
            .$this->request->start_date->format('j M')
            .' – '.$this->request->end_date->format('j M')
            ." ({$this->request->days} days)";
    }

    public function url(): string
    {
        return '/leave';
    }
}
