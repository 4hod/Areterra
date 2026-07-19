<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveBalance extends Model
{
    use HasFactory;

    protected $guarded = [];

    // Mirror the DB default so freshly created rows read correctly (SPEC.md: 28 days).
    protected $attributes = [
        'entitlement_days' => 28,
    ];

    protected function casts(): array
    {
        return [
            'entitlement_days' => 'decimal:1',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function remainingFor(User $user, ?int $year = null): array
    {
        $year ??= now()->year;

        $entitlement = (float) (static::firstOrCreate(
            ['user_id' => $user->id, 'year' => $year],
        )->entitlement_days);

        $taken = (float) LeaveRequest::where('user_id', $user->id)
            ->where('type', 'annual')
            ->where('status', 'approved')
            ->whereYear('start_date', $year)
            ->sum('days');

        return [
            'entitlement' => $entitlement,
            'taken' => $taken,
            'remaining' => $entitlement - $taken,
        ];
    }
}
