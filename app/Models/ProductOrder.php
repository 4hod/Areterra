<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductOrder extends Model
{
    use HasFactory;

    protected $guarded = [];

    public const STATUSES = ['pending', 'approved', 'rejected', 'ordered', 'delivered'];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'ordered_at' => 'datetime',
            'delivered_at' => 'datetime',
            'expected_delivery_date' => 'date',
            'quantity' => 'decimal:2',
            'cost' => 'decimal:2',
        ];
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeOpen($query)
    {
        return $query->whereNotIn('status', ['rejected', 'delivered']);
    }
}
