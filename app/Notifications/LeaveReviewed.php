<?php

namespace App\Notifications;

use App\Models\LeaveRequest;

class LeaveReviewed extends HubNotification
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
        return $this->request->status === 'approved'
            ? '✅ Leave approved'
            : '❌ Leave declined';
    }

    public function body(): string
    {
        $body = ucfirst($this->request->type).' leave '
            .$this->request->start_date->format('j M')
            .' – '.$this->request->end_date->format('j M')
            .' has been '.$this->request->status.'.';

        if ($this->request->review_notes) {
            $body .= ' Note: '.$this->request->review_notes;
        }

        return $body;
    }

    public function url(): string
    {
        return '/leave';
    }
}
