<?php

namespace App\Http\Controllers;

use App\Models\AutomationRule;
use App\Models\AutomationRun;
use App\Support\AutomationEngine;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AutomationController extends Controller
{
    public function index(Request $request, AutomationEngine $engine)
    {
        $engine->ensureSystemRules($request->user()->id);

        return Inertia::render('Automations', [
            'rules' => AutomationRule::orderBy('id')->get()->map(fn ($rule) => [
                'id' => $rule->id, 'name' => $rule->name, 'trigger' => $rule->trigger,
                'action' => $rule->action, 'active' => $rule->active,
                'last_run_at' => $rule->last_run_at?->toIso8601String(),
            ]),
            'runs' => AutomationRun::with('rule:id,name')->latest('ran_at')->limit(40)->get()->map(fn ($run) => [
                'id' => $run->id, 'rule' => $run->rule?->name ?? $run->trigger,
                'status' => $run->status, 'summary' => $run->summary,
                'ran_at' => $run->ran_at->toIso8601String(),
            ]),
        ]);
    }

    public function toggle(Request $request, AutomationRule $automationRule)
    {
        $data = $request->validate(['active' => ['required', 'boolean']]);
        $automationRule->update($data);

        return back()->with('success', $automationRule->active ? 'Automation enabled.' : 'Automation paused.');
    }

    public function run(AutomationEngine $engine)
    {
        $count = $engine->scanScheduled();

        return back()->with('success', "Automation scan complete — {$count} eligible records checked.");
    }
}
