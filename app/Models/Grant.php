<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Grant extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function expenditures()
    {
        return $this->hasMany(GrantExpenditure::class);
    }

    public function inKindDonations()
    {
        return $this->hasMany(InKindDonation::class);
    }

    public function spent(): float
    {
        return (float) $this->expenditures()->sum('amount');
    }
}
