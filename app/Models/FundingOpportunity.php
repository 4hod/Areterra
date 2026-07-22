<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FundingOpportunity extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    public const STATUSES = ['identified', 'applied', 'awarded', 'declined'];

    protected function casts(): array
    {
        return [
            'deadline' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
