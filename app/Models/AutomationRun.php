<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AutomationRun extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'summary' => 'encrypted',
            'ran_at' => 'datetime',
        ];
    }

    public function rule() { return $this->belongsTo(AutomationRule::class, 'automation_rule_id'); }
    public function subject() { return $this->morphTo(); }
}
