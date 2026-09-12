<?php

namespace App\Models;

use App\Models\Concerns\HasTasks;
use App\Models\Concerns\HasDocuments;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ComplianceItem extends Model
{
    use HasFactory, SoftDeletes, HasTasks, HasDocuments;

    protected $guarded = [];

    /** The vehicle, animal or member this compliance deadline is about. */
    public function relatesTo()
    {
        // The name MUST be passed explicitly. A bare morphTo() infers the column
        // prefix from the method name and would look for `relatesTo_type`,
        // which does not exist — the columns are `relates_to_type/_id`.
        return $this->morphTo('relates_to');
    }

    public function completer()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function isOverdue(): bool
    {
        return $this->completed_at === null && $this->due_date->lt(today());
    }
}
