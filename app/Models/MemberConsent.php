<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MemberConsent extends Model
{
    use HasFactory;

    public const TYPES = ['photos', 'outings', 'medication', 'data_sharing', 'emergency_treatment'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'granted' => 'boolean',
            'recorded_on' => 'date',
        ];
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    // Consent is treated as expiring a year after it was recorded — re-confirm
    // annually rather than treating it as a one-time, forever tick.
    public function expiresAt(): ?\Carbon\Carbon
    {
        return $this->recorded_on?->copy()->addYear();
    }

    public function isExpired(): bool
    {
        return $this->granted && $this->expiresAt()?->lt(today());
    }
}
