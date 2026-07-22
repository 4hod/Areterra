<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormField extends Model
{
    use HasFactory;

    protected $guarded = [];

    public const TYPES = ['text', 'textarea', 'select', 'checkbox', 'date', 'number'];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_required' => 'boolean',
        ];
    }

    public function form()
    {
        return $this->belongsTo(FormDefinition::class, 'form_id');
    }
}
