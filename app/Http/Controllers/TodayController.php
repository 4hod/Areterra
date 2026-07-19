<?php

namespace App\Http\Controllers;

use App\Services\TodayChecklist;
use Inertia\Inertia;

class TodayController extends Controller
{
    public function __invoke(TodayChecklist $checklist)
    {
        return Inertia::render('Today', [
            'checklist' => $checklist->build(today()),
            'date' => today()->toDateString(),
        ]);
    }
}
