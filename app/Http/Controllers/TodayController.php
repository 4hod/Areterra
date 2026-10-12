<?php

namespace App\Http\Controllers;

use App\Models\DailyShiftCompletion;
use App\Services\TodayChecklist;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TodayController extends Controller
{
    public function __invoke(Request $request, TodayChecklist $checklist)
    {
        return Inertia::render('Today', [
            'checklist' => $checklist->build(today()),
            'date' => today()->toDateString(),
            'shiftCompletedAt' => DailyShiftCompletion::query()
                ->where('user_id', $request->user()->id)
                ->whereDate('work_date', today())
                ->value('completed_at'),
        ]);
    }

    public function finish(Request $request, TodayChecklist $checklist): RedirectResponse
    {
        $applicable = collect($checklist->build(today()))->where('applicable', true);

        if ($applicable->isEmpty() || $applicable->contains(fn (array $item) => ! $item['done'])) {
            return back()->with('error', 'Finish every required job before ending today.');
        }

        DailyShiftCompletion::query()->updateOrCreate(
            ['user_id' => $request->user()->id, 'work_date' => today()->toDateString()],
            ['completed_at' => now()],
        );

        return back()->with('success', 'Your work for today is finished.');
    }
}
