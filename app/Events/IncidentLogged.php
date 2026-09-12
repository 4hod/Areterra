<?php

namespace App\Events;

use App\Models\Incident;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IncidentLogged
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Incident $incident) {}
}
