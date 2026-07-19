<?php

namespace App\Http\Controllers;

use App\Models\RiskAssessment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class RiskAssessmentController extends Controller
{
    public function index()
    {
        return Inertia::render('RiskAssessments', [
            'assessments' => RiskAssessment::with('signedOffBy:id,name')
                ->orderBy('title')
                ->get()
                ->map(fn ($r) => [
                    'id' => $r->id,
                    'title' => $r->title,
                    'description' => $r->description,
                    'likelihood' => $r->likelihood,
                    'severity' => $r->severity,
                    'score' => $r->riskScore(),
                    'level' => $r->riskLevel(),
                    'control_measures' => $r->control_measures,
                    'review_date' => $r->review_date?->toDateString(),
                    'status' => $r->status,
                    'signed_off_by' => $r->signedOffBy?->name,
                    'signed_off_at' => $r->signed_off_at?->toDateString(),
                ]),
            'canManage' => Gate::allows('manage_compliance'),
        ]);
    }

    public function store(Request $request)
    {
        RiskAssessment::create([
            ...$this->validated($request),
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Risk assessment created.');
    }

    public function update(Request $request, RiskAssessment $riskAssessment)
    {
        $riskAssessment->update($this->validated($request));

        return back()->with('success', 'Risk assessment saved.');
    }

    public function signOff(Request $request, RiskAssessment $riskAssessment)
    {
        $riskAssessment->update([
            'signed_off_by' => $request->user()->id,
            'signed_off_at' => now(),
            'status' => 'active',
        ]);

        return back()->with('success', 'Signed off.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'likelihood' => ['required', 'integer', 'between:1,5'],
            'severity' => ['required', 'integer', 'between:1,5'],
            'control_measures' => ['nullable', 'string'],
            'review_date' => ['nullable', 'date'],
            'status' => ['sometimes', 'in:draft,active,archived'],
        ]);
    }
}
