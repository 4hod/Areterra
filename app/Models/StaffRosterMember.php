<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StaffRosterMember extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'staff_roster';

    protected $guarded = [];

    /** The same human's login account, when they have one. Payroll used to
     *  match these by name, which meant one person could be paid twice. */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'ni_number' => 'encrypted',
        ];
    }

    public function rates()
    {
        return $this->morphMany(PayrollRate::class, 'payable');
    }

    public function currentRate(): ?float
    {
        $rate = $this->rates()->where('effective_from', '<=', today())
            ->orderByDesc('effective_from')->first();

        return $rate ? (float) $rate->hourly_rate : null;
    }

    // Reading an encrypted attribute can throw if it was written under a
    // different APP_KEY (e.g. after a key rotation). One bad row shouldn't
    // crash an entire page — surface it as null instead, so it just shows
    // blank and can be re-entered, rather than a 500.
    public function safeNiNumber(): ?string
    {
        try {
            return $this->ni_number;
        } catch (\Illuminate\Contracts\Encryption\DecryptException) {
            return null;
        }
    }
}
