<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransportLedgerEntry extends Model
{
    use HasFactory;

    /** A return journey. Kept for display and credit maths. */
    public const DAILY_RATE = 5.00;

    /** One leg — morning collection or afternoon drop-off. */
    public const LEG_RATE = 2.50;

    protected $table = 'transport_ledger';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'charge_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function reverses()
    {
        return $this->belongsTo(self::class, 'reverses_id');
    }

    public function reversal()
    {
        return $this->hasOne(self::class, 'reverses_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    // Balance = payments − charges. Positive = in credit, negative = owes.
    public static function balanceFor(int $memberId): float
    {
        return (float) static::where('member_id', $memberId)
            ->selectRaw("coalesce(sum(case when type = 'payment' then amount else -amount end), 0) as balance")
            ->value('balance');
    }
}
