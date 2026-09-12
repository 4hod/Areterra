<?php

namespace App\Models;

use App\Models\Concerns\HasLedgerEntries;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransportRun extends Model
{
    use HasFactory, HasLedgerEntries;

    protected $guarded = [];

    /** The staff member who drove the run. */
    public function driver()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    protected function casts(): array
    {
        return [
            'run_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }
}
