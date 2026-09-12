<?php

namespace App\Events;

use App\Models\SafeguardingConcern;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SafeguardingConcernRaised
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly SafeguardingConcern $concern) {}
}
