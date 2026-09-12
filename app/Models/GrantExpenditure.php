<?php

namespace App\Models;

use App\Models\Concerns\HasLedgerEntries;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GrantExpenditure extends Model
{
    use HasFactory, HasLedgerEntries;

    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'spent_date' => 'date',
        ];
    }

    public function grant()
    {
        return $this->belongsTo(Grant::class);
    }
}
