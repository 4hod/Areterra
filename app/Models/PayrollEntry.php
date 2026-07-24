<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollEntry extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'ni_number' => 'encrypted',
            'hourly_rate' => 'decimal:2',
            'total_hours' => 'decimal:2',
            'basic_pay' => 'decimal:2',
            'holiday_pay' => 'decimal:2',
            'total_ssp' => 'decimal:2',
            'mileage' => 'decimal:1',
            'mileage_pay' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function period()
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function payable()
    {
        return $this->morphTo();
    }

    // See StaffRosterMember::safeNiNumber() — same defensive reasoning.
    public function safeNiNumber(): ?string
    {
        try {
            return $this->ni_number;
        } catch (\Illuminate\Contracts\Encryption\DecryptException) {
            return null;
        }
    }

    // basic = rate × hours; total = basic + holiday + SSP + mileage pay.
    public static function computeTotals(array $e): array
    {
        $basic = round(((float) ($e['hourly_rate'] ?? 0)) * ((float) ($e['total_hours'] ?? 0)), 2);

        return [
            'basic_pay' => $basic,
            'total' => round(
                $basic
                + (float) ($e['holiday_pay'] ?? 0)
                + (float) ($e['total_ssp'] ?? 0)
                + (float) ($e['mileage_pay'] ?? 0),
                2,
            ),
        ];
    }
}
