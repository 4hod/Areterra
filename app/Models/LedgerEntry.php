<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LedgerEntry extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['entry_date' => 'date', 'amount' => 'decimal:2', 'restricted' => 'boolean'];
    }

    public function source()
    {
        return $this->morphTo();
    }

    public function grant()
    {
        return $this->belongsTo(Grant::class);
    }

    public function reverses()
    {
        return $this->belongsTo(self::class, 'reverses_id');
    }

    public function reversal()
    {
        return $this->hasOne(self::class, 'reverses_id');
    }

    /** A reversed entry and its reversal cancel out — exclude both from totals. */
    public function scopeEffective($query)
    {
        return $query->whereNull('reverses_id')
            ->whereDoesntHave('reversal');
    }

    public function scopeInPeriod($query, \App\Support\Period $period)
    {
        return $query->whereBetween('entry_date', [$period->start->toDateString(), $period->end->toDateString()]);
    }

    public function scopeUnrestricted($query)
    {
        return $query->where('restricted', false);
    }
}
