<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Member extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'dob' => 'date',
            'nhs_number' => 'encrypted',
            'support_needs' => 'encrypted',
            'diagnoses' => 'encrypted',
            'medication' => 'encrypted',
            'emergency_contacts' => 'encrypted:array',
        ];
    }

    public function settings()
    {
        return $this->hasOne(MemberSetting::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function endOfDayRecords()
    {
        return $this->hasMany(EndOfDayRecord::class);
    }

    public function reviews()
    {
        return $this->hasMany(MemberReview::class);
    }

    public function transportLedger()
    {
        return $this->hasMany(TransportLedgerEntry::class);
    }

    public function transportRuns()
    {
        return $this->hasMany(TransportRun::class);
    }

    public function abcObservations()
    {
        return $this->hasMany(AbcObservation::class);
    }

    public function bodyMaps()
    {
        return $this->hasMany(BodyMap::class);
    }

    public function invoices()
    {
        return $this->hasMany(MemberInvoice::class);
    }

    public function contacts()
    {
        return $this->hasMany(MemberContact::class);
    }

    public function goals()
    {
        return $this->hasMany(MemberGoal::class);
    }

    public function outcomes()
    {
        return $this->hasMany(MemberOutcome::class);
    }

    public function alerts()
    {
        return $this->hasMany(MemberAlert::class);
    }

    public function consents()
    {
        return $this->hasMany(MemberConsent::class);
    }

    public function commsLog()
    {
        return $this->hasMany(CommsLog::class);
    }

    public function displayName(): string
    {
        $first = $this->preferred_name ?: $this->first_name;

        return trim("{$first} {$this->last_name}");
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // Members whose attendance_days (ISO day numbers) include the given date's day.
    public function scopeScheduledFor($query, \Carbon\CarbonInterface $date)
    {
        return $query->active()->whereHas('settings', function ($q) use ($date) {
            $q->whereJsonContains('attendance_days', $date->isoWeekday());
        });
    }
}
