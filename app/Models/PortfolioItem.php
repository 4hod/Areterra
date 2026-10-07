<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PortfolioItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'title' => 'encrypted',
            'description' => 'encrypted',
            'achieved_on' => 'date',
            'visible_to_member' => 'boolean',
        ];
    }

    public function member() { return $this->belongsTo(Member::class); }
    public function source() { return $this->morphTo(); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function certificate() { return $this->hasOne(Certificate::class); }
}
