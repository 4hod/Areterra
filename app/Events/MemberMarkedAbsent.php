<?php

namespace App\Events;

use App\Models\Attendance;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MemberMarkedAbsent
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Attendance $attendance) {}
}
