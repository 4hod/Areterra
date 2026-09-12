<?php

namespace App\Listeners;

use App\Events\TransportOutcomeRecorded;
use App\Support\Ledger;

/**
 * Point 4 for vehicles: mileage on a completed run becomes a vehicle cost in
 * Finance, attributed to the vehicle that did the run. Nothing is retyped.
 */
class PostTransportCostToLedger
{
    public function handle(TransportOutcomeRecorded $event): void
    {
        $run = $event->run;

        // Only a leg actually travelled costs fuel.
        if ($run->outcome !== 'collected' || ! $run->vehicle_id || ! $run->mileage) {
            return;
        }

        $rate = (float) config('periods.mileage_rate', 0.45);

        Ledger::post(
            source: $run,
            direction: 'expense',
            category: 'vehicle_costs',
            description: 'Transport mileage — '.($run->vehicle->registration ?? "vehicle #{$run->vehicle_id}"),
            amount: round(((float) $run->mileage) * $rate, 2),
            date: $run->run_date->toDateString(),
        );
    }
}
