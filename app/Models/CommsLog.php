<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CommsLog extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPES = ['email', 'phone', 'letter', 'meeting', 'text', 'other'];

    protected $table = 'comms_log';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'summary' => 'encrypted',
        ];
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
