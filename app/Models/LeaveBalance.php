<?php

namespace App\Models;

use App\Support\LeaveCalendar;
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

        $taken = LeaveRequest::where('user_id', $user->id)
            ->where('type', 'annual')
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', "{$year}-12-31")
            ->whereDate('end_date', '>=', "{$year}-01-01")
            ->get()
            ->sum(function (LeaveRequest $leave) use ($user, $year): float {
                if ($leave->days_by_year !== null) {
                    return (float) ($leave->days_by_year[(string) $year] ?? $leave->days_by_year[$year] ?? 0);
                }

                // Old records are recalculated consistently instead of charging
                // their whole total to the year in which they started.
                return (float) (LeaveCalendar::daysByYear(
                    $user,
                    $leave->start_date,
                    $leave->end_date,
                    $leave->start_half_day,
                    $leave->end_half_day,
                )[$year] ?? 0);
            });

        return [
            'entitlement' => $entitlement,
            'taken' => $taken,
            'remaining' => $entitlement - $taken,
        ];
    }
}
