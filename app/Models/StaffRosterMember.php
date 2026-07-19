<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StaffRosterMember extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'staff_roster';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'ni_number' => 'encrypted',
        ];
    }

    public function rates()
    {
        return $this->morphMany(PayrollRate::class, 'payable');
    }

    public function currentRate(): ?float
    {
        $rate = $this->rates()->where('effective_from', '<=', today())
            ->orderByDesc('effective_from')->first();

        return $rate ? (float) $rate->hourly_rate : null;
    }
}
