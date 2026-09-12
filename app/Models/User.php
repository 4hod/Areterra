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

#[Fillable(['name', 'email', 'password', 'role', 'job_title', 'phone', 'bio', 'photo_path'])]
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

    protected static function booted(): void
    {
        // A new account starts with its role's preset ticked. From then on the
        // account owns its permissions and the preset is just history.
        static::created(function (User $user) {
            if ($user->capabilityGrants()->exists()) {
                return;
            }

            foreach (static::preset($user->role) as $capability) {
                $user->capabilityGrants()->create(['capability' => $capability]);
            }

            $user->cachedCapabilities = null;
        });
    }

    public function isAdministrator(): bool
    {
        return $this->role === 'administrator';
    }

    public function hasCapability(string $capability): bool
    {
        return in_array($capability, $this->capabilities(), true);
    }

    /**
     * Capabilities are stored per user, not derived from their role. The role
     * is only the preset their account was set up from.
     *
     * Cached for the life of the request — the gate asks this a lot.
     */
    public function capabilities(): array
    {
        return $this->cachedCapabilities ??= $this->capabilityGrants()
            ->pluck('capability')
            ->all();
    }

    /** @var array<int, string>|null */
    private ?array $cachedCapabilities = null;

    public function capabilityGrants()
    {
        return $this->hasMany(UserCapability::class);
    }

    /**
     * Replace this user's permissions with exactly the ones given. Anything not
     * in the list is removed. Unknown capabilities are ignored rather than
     * stored, so a stale form can't grant something that no longer exists.
     *
     * @param  array<int, string>  $capabilities
     */
    public function syncCapabilities(array $capabilities, ?User $grantedBy = null): void
    {
        $valid = array_values(array_intersect(config('capabilities.all'), $capabilities));

        $this->capabilityGrants()->whereNotIn('capability', $valid)->delete();

        $existing = $this->capabilityGrants()->pluck('capability')->all();

        foreach (array_diff($valid, $existing) as $capability) {
            $this->capabilityGrants()->create([
                'capability' => $capability,
                'granted_by' => $grantedBy?->id,
            ]);
        }

        $this->cachedCapabilities = null;
        $this->unsetRelation('capabilityGrants');
    }

    /** The capability list a preset would give, for pre-filling the form. */
    public static function preset(string $role): array
    {
        return config("capabilities.roles.{$role}", []);
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
