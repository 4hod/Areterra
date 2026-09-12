<?php

namespace App\Models\Concerns;

use App\Models\Incident;

/** Incidents recorded against this member, animal or vehicle. */
trait HasIncidents
{
    public function incidents()
    {
        return $this->morphMany(Incident::class, 'subject')->latest('occurred_at');
    }
}
