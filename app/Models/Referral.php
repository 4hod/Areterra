<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Referral extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'details' => 'encrypted',
            'referrer_name' => 'encrypted',
            'referrer_email' => 'encrypted',
            'referrer_phone' => 'encrypted',
            'organisation' => 'encrypted',
            'person_name' => 'encrypted',
            'reviewed_at' => 'datetime',
        ];
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
