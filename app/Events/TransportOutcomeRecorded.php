<?php

namespace App\Events;

use App\Models\TransportRun;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** A leg was recorded as collected, not collected, or absent. */
class TransportOutcomeRecorded
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly TransportRun $run) {}
}
