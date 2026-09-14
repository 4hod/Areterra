<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegacyImportRow extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payload' => 'encrypted:array',
            'submitted_at' => 'datetime',
            'recorded_at' => 'datetime',
        ];
    }
}
