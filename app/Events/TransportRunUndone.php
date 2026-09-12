<?php

namespace App\Events;

use App\Models\TransportRun;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TransportRunUndone
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly TransportRun $run) {}
}
