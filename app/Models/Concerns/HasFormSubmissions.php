<?php

namespace App\Models\Concerns;

use App\Models\FormSubmission;

/** Reports staff have filled in about this record. */
trait HasFormSubmissions
{
    public function formSubmissions()
    {
        return $this->morphMany(FormSubmission::class, 'subject')->latest('submitted_at');
    }
}
