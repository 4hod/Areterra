<?php

namespace App\Http\Controllers;

use App\Models\InsurancePolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class InsurancePolicyController extends Controller
{
    public function index()
    {
        return \Inertia\Inertia::render('Insurance', [
            'policies' => InsurancePolicy::orderBy('renewal_date')->get()
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'policy_type' => $p->policy_type,
                    'provider' => $p->provider,
                    'policy_number' => $p->policy_number,
                    'renewal_date' => $p->renewal_date?->toDateString(),
                    'annual_cost' => $p->annual_cost !== null ? (float) $p->annual_cost : null,
                    'notes' => $p->notes,
                ]),
            'canManage' => Gate::allows('manage_operations'),
        ]);
    }

    public function store(Request $request)
    {
        InsurancePolicy::create([
            ...$request->validate([
                'policy_type' => ['required', 'string', 'max:150'],
                'provider' => ['nullable', 'string', 'max:150'],
                'policy_number' => ['nullable', 'string', 'max:100'],
                'renewal_date' => ['nullable', 'date'],
                'annual_cost' => ['nullable', 'numeric', 'min:0'],
                'notes' => ['nullable', 'string'],
            ]),
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Insurance policy added.');
    }

    public function update(Request $request, InsurancePolicy $policy)
    {
        $policy->update($request->validate([
            'policy_type' => ['sometimes', 'string', 'max:150'],
            'provider' => ['nullable', 'string', 'max:150'],
            'policy_number' => ['nullable', 'string', 'max:100'],
            'renewal_date' => ['nullable', 'date'],
            'annual_cost' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]));

        return back()->with('success', 'Insurance policy updated.');
    }

    public function destroy(InsurancePolicy $policy)
    {
        $policy->delete();

        return back()->with('success', "\"{$policy->policy_type}\" removed.");
    }
}
