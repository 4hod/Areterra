<?php

namespace App\Listeners;

use App\Events\MemberMarkedAbsent;
use App\Support\AutomationEngine;

class RunAutomationRules
{
    public function __construct(private AutomationEngine $engine) {}

    public function handle(MemberMarkedAbsent $event): void
    {
        $this->engine->run('member_absent', $event->attendance, ['occurrence' => $event->attendance->date->toDateString()]);
    }
}
