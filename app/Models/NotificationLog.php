<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    protected $table = 'notification_log';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    // True the first time a key is seen; false on any repeat. Backs the
    // once-per-day guarantee for scheduled reminders (SPEC.md §22).
    public static function claim(string $key): bool
    {
        try {
            static::create(['key' => $key, 'sent_at' => now()]);

            return true;
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            return false;
        }
    }
}
