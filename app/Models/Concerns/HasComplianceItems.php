<?php

namespace App\Models\Concerns;

use App\Models\ComplianceItem;

/**
 * Compliance owns deadlines; the record is the subject. A vehicle's MOT is a
 * compliance item that relates to the vehicle, not a date column on it.
 */
trait HasComplianceItems
{
    public function complianceItems()
    {
        return $this->morphMany(ComplianceItem::class, 'relates_to')->orderBy('due_date');
    }

    public function openComplianceItems()
    {
        return $this->complianceItems()->whereNull('completed_at');
    }
}
