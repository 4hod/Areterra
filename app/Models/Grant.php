<?php

namespace App\Models;

use App\Models\Concerns\HasFormSubmissions;

use App\Models\Concerns\HasLedgerEntries;

use App\Models\Concerns\HasTasks;
use App\Models\Concerns\HasDocuments;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Grant extends Model
{
    use HasFactory, SoftDeletes, HasTasks, HasDocuments, HasLedgerEntries, HasFormSubmissions;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function expenditures()
    {
        return $this->hasMany(GrantExpenditure::class);
    }

    public function inKindDonations()
    {
        return $this->hasMany(InKindDonation::class);
    }

    public function spent(): float
    {
        return (float) $this->expenditures()->sum('amount');
    }
}
