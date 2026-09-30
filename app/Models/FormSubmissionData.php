<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormSubmissionData extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['value' => 'encrypted'];
    }

    public function field()
    {
        return $this->belongsTo(FormField::class, 'field_id');
    }

    public function submission()
    {
        return $this->belongsTo(FormSubmission::class, 'submission_id');
    }
}
