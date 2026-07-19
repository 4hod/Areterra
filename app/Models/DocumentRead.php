<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentRead extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }
}
