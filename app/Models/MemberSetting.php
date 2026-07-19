<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MemberSetting extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'transport_required' => 'boolean',
            'attendance_days' => 'array',
        ];
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function keyWorker()
    {
        return $this->belongsTo(User::class, 'key_worker_id');
    }
}
