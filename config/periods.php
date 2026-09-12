<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Finance period anchor
    |--------------------------------------------------------------------------
    |
    | The first day of period 0. Set this to the start date of a 4-week finance
    | period you know is correct, then leave it alone — changing it renumbers
    | every period in the Hub. Must be a Monday for period boundaries to line up
    | with operating weeks.
    |
    */

    'anchor' => env('PERIOD_ANCHOR', '2026-01-05'),

    /*
    |--------------------------------------------------------------------------
    | Billing
    |--------------------------------------------------------------------------
    |
    | 'cycle_weeks'   — default invoicing cadence (2 or 4).
    | 'advance'       — true if members are invoiced before the cycle they cover.
    | 'payroll_cutoff_days' — days before pay date that hours must be finalised.
    |
    */

    'cycle_weeks' => env('BILLING_CYCLE_WEEKS', 4),

    'advance' => env('BILLING_IN_ADVANCE', true),

    'payroll_cutoff_days' => env('PAYROLL_CUTOFF_DAYS', 3),

    // HMRC approved mileage rate, used to cost transport runs.
    'mileage_rate' => env('MILEAGE_RATE', 0.45),

];
