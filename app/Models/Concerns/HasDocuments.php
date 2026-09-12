<?php

namespace App\Models\Concerns;

use App\Models\Document;

/**
 * Point 10 — documents belong to records, not folders. The Documents section
 * becomes a global view over these links rather than a second filing system.
 */
trait HasDocuments
{
    public function documents()
    {
        return $this->morphMany(Document::class, 'attachable')->latest();
    }
}
