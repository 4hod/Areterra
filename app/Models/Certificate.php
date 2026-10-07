<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Certificate extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'title' => 'encrypted',
            'description' => 'encrypted',
            'issued_on' => 'date',
        ];
    }

    public function member() { return $this->belongsTo(Member::class); }
    public function portfolioItem() { return $this->belongsTo(PortfolioItem::class); }
    public function issuer() { return $this->belongsTo(User::class, 'issued_by'); }
}
