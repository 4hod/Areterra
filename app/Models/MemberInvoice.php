<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MemberInvoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'invoice_date' => 'date',
            'due_date' => 'date',
            'paid_date' => 'date',
        ];
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    // Sent invoices past their due date auto-read as overdue (SPEC.md §16).
    public function effectiveStatus(): string
    {
        if ($this->status === 'sent' && $this->due_date?->lt(today())) {
            return 'overdue';
        }

        return $this->status;
    }
}
