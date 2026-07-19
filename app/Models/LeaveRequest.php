<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveRequest extends Model
{
    use HasFactory;

    public const TYPES = ['annual', 'sick', 'compassionate', 'unpaid', 'toil', 'other'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'days' => 'decimal:1',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // Weekdays between the two dates, inclusive.
    public static function weekdaysBetween(\Carbon\CarbonInterface $start, \Carbon\CarbonInterface $end): float
    {
        $days = 0;
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            if ($d->isWeekday()) {
                $days++;
            }
        }

        return (float) $days;
    }
}
