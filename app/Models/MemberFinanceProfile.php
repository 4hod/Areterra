<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberFinanceProfile extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'custom_day_rate' => 'decimal:2',
            'one_to_one_hours_per_week' => 'decimal:2',
            'custom_one_to_one_rate' => 'decimal:2',
            'charge_transport' => 'boolean',
            'custom_transport_rate' => 'decimal:2',
        ];
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }
}
