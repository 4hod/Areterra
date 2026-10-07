<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AutomationRule extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'action_config' => 'array',
            'active' => 'boolean',
            'system' => 'boolean',
            'last_run_at' => 'datetime',
        ];
    }

    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function runs() { return $this->hasMany(AutomationRun::class); }
}
