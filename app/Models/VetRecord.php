<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VetRecord extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'visit_date' => 'date',
        ];
    }

    public function animal()
    {
        return $this->belongsTo(Animal::class);
    }
}
