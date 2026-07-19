<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationPref extends Model
{
    public const CATEGORIES = ['announcements', 'concerns', 'leave', 'reminders'];

    protected $guarded = [];

    // Explicit defaults so a freshly created row behaves correctly without a
    // refresh — DB column defaults are not hydrated onto new model instances.
    protected $attributes = [
        'push_enabled' => true,
        'email_enabled' => true,
    ];

    protected function casts(): array
    {
        return [
            'push_enabled' => 'boolean',
            'email_enabled' => 'boolean',
            'categories' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function categoryEnabled(string $category): bool
    {
        return ($this->categories[$category] ?? true) !== false;
    }
}
