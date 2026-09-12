<?php

namespace App\Events;

use App\Models\WelfareCheck;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Domain event. Anything that should happen *because* of this — finance
 * postings, notifications, timeline entries, task completion — belongs in a
 * listener in app/Listeners, not in the controller that fired it.
 */
class AnimalHealthConcernRaised
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly WelfareCheck $concern) {}
}
