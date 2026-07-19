<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use NotificationChannels\WebPush\HasPushSubscriptions;

#[Fillable(['name', 'email', 'password', 'role', 'job_title'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasPushSubscriptions, Notifiable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isAdministrator(): bool
    {
        return $this->role === 'administrator';
    }

    public function hasCapability(string $capability): bool
    {
        return in_array($capability, config('capabilities.roles')[$this->role] ?? [], true);
    }

    public function capabilities(): array
    {
        if ($this->isAdministrator()) {
            return array_values(array_unique(array_merge(...array_values(config('capabilities.roles')))));
        }

        return config('capabilities.roles')[$this->role] ?? [];
    }

    public function scopeManagers($query)
    {
        return $query->whereIn('role', ['manager', 'administrator']);
    }

    public function notificationPref()
    {
        return $this->hasOne(NotificationPref::class);
    }

    public function pref(): NotificationPref
    {
        return $this->notificationPref()->firstOrCreate([]);
    }

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function timeclockEntries()
    {
        return $this->hasMany(TimeclockEntry::class);
    }

    public function supervisions()
    {
        return $this->morphMany(Supervision::class, 'subject');
    }

    public function rates()
    {
        return $this->morphMany(PayrollRate::class, 'payable');
    }
}
