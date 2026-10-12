<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WelfareCheck extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'concern' => 'boolean',
            'fed' => 'boolean',
            'pellets_given' => 'boolean',
            'greenery_given' => 'boolean',
            'fresh_water' => 'boolean',
            'hay_topped_up' => 'boolean',
            'enclosure_cleaned' => 'boolean',
            'treats_given' => 'boolean',
        ];
    }

    public function animal()
    {
        return $this->belongsTo(Animal::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
