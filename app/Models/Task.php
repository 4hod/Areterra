<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    /** The member, animal, grant, invoice or compliance item this is about. */
    public function taskable()
    {
        return $this->morphTo();
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function completer()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function scopeOpen($query)
    {
        return $query->whereNull('completed_at');
    }

    public function scopeOverdue($query)
    {
        return $query->open()->whereDate('due_date', '<', today());
    }

    public function isComplete(): bool
    {
        return $this->completed_at !== null;
    }
}
