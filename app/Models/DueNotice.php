<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DueNotice extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['due_on' => 'date', 'notified_at' => 'datetime'];
    }

    public function subject()
    {
        return $this->morphTo();
    }
}
