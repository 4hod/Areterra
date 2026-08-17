<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SarRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    public const STATUSES = ['pending', 'in_progress', 'fulfilled', 'declined'];

    protected function casts(): array
    {
        return [
            'received_date' => 'date',
            'deadline_date' => 'date',
            'fulfilled_date' => 'date',
        ];
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function loggedBy()
    {
        return $this->belongsTo(User::class, 'logged_by');
    }

    public function isOverdue(): bool
    {
        return ! in_array($this->status, ['fulfilled', 'declined'], true) && $this->deadline_date->lt(today());
    }
}
