<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecognitionLike extends Model
{
    protected $guarded = [];

    public function recognition()
    {
        return $this->belongsTo(Recognition::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
