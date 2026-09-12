<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** One permission held by one person. */
#[Fillable(['user_id', 'capability', 'granted_by'])]
class UserCapability extends Model
{
    protected $table = 'user_capabilities';

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function grantedBy()
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}
