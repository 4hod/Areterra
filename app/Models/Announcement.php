<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Announcement extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    public function author()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reads()
    {
        return $this->hasMany(AnnouncementRead::class);
    }

    public function readBy(User $user): bool
    {
        return $this->reads()->where('user_id', $user->id)->exists();
    }
}
