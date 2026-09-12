<?php

namespace App\Models;

use App\Models\Concerns\HasFormSubmissions;

use App\Models\Concerns\HasComplianceItems;
use App\Models\Concerns\HasLedgerEntries;
use App\Models\Concerns\HasTasks;
use App\Models\Concerns\HasDocuments;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use HasFactory, SoftDeletes, HasComplianceItems, HasLedgerEntries, HasTasks, HasDocuments, HasFormSubmissions;

    protected $guarded = [];

    public function transportRuns()
    {
        return $this->hasMany(TransportRun::class);
    }

    /**
     * Incidents involving this vehicle. Kept as the direct foreign key —
     * HasIncidents' polymorphic version is removed from this model so there is
     * exactly one definition rather than a silent override.
     */
    public function incidents()
    {
        return $this->hasMany(Incident::class)->latest('occurred_at');
    }

    protected function casts(): array
    {
        return [
            'mot_due' => 'date',
            'service_due' => 'date',
            'active' => 'boolean',
        ];
    }

    public function defects()
    {
        return $this->hasMany(VehicleDefect::class);
    }

    public function openDefects()
    {
        return $this->defects()->whereNull('resolved_at');
    }
}
