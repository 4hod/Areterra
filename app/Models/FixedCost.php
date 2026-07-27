<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FixedCost extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'active' => 'boolean', 'start_date' => 'date', 'end_date' => 'date'];
    }
}
