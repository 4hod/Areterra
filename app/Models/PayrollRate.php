<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollRate extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'hourly_rate' => 'decimal:2',
        ];
    }

    public function payable()
    {
        return $this->morphTo();
    }
}
