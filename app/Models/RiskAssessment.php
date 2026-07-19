<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RiskAssessment extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'review_date' => 'date',
            'signed_off_at' => 'datetime',
        ];
    }

    public function signedOffBy()
    {
        return $this->belongsTo(User::class, 'signed_off_by');
    }

    public function riskScore(): int
    {
        return $this->likelihood * $this->severity;
    }

    public function riskLevel(): string
    {
        $score = $this->riskScore();

        return match (true) {
            $score >= 15 => 'red',
            $score >= 8 => 'amber',
            default => 'green',
        };
    }
}
