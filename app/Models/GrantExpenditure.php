<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GrantExpenditure extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'spent_date' => 'date',
        ];
    }

    public function grant()
    {
        return $this->belongsTo(Grant::class);
    }
}
