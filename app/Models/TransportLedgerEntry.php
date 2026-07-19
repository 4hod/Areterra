<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransportLedgerEntry extends Model
{
    use HasFactory;

    public const DAILY_RATE = 5.00;

    protected $table = 'transport_ledger';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'amount' => 'decimal:2',
        ];
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
