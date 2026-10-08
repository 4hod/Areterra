<?php

namespace App\Http\Controllers;

use App\Models\RiskAssessment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class RiskAssessmentController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:50'],
            'history' => ['nullable', 'boolean'],
        ]);

        $query = RiskAssessment::with('signedOffBy:id,name')
            ->when(! ($filters['history'] ?? false), fn ($q) => $q->where('is_current', true))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(function ($inner) use ($term) {
                $inner->where('title', 'like', "%{$term}%")->orWhere('description', 'like', "%{$term}%")
                    ->orWhere('control_measures', 'like', "%{$term}%");
            }))
            ->when($filters['category'] ?? null, fn ($q, $category) => $q->where('category', $category));

        return Inertia::render('RiskAssessments', [
            'assessments' => $query->orderBy('category')->orderBy('title')->orderByDesc('version')
                ->get()
                ->map(fn ($r) => [
                    'id' => $r->id,
                    'title' => $r->title,
                    'category' => $r->category,
                    'version' => $r->version,
                    'is_current' => $r->is_current,
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
            'filters' => [
                'q' => $filters['q'] ?? '', 'category' => $filters['category'] ?? '',
                'history' => (bool) ($filters['history'] ?? false),
            ],
            'categories' => RiskAssessment::query()->distinct()->orderBy('category')->pluck('category'),
            'canManage' => Gate::allows('manage_compliance'),
        ]);
    }

    public function store(Request $request)
    {
        RiskAssessment::create([
            ...$this->validated($request),
            'status' => 'draft',
            'version' => 1,
            'is_current' => true,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Risk assessment created.');
    }

    public function newVersion(Request $request, RiskAssessment $riskAssessment)
    {
        abort_unless($riskAssessment->is_current, 422, 'Only the current version can be revised.');

        $next = \Illuminate\Support\Facades\DB::transaction(function () use ($request, $riskAssessment) {
            $riskAssessment->update(['is_current' => false, 'status' => 'archived']);

            return RiskAssessment::create([
                'title' => $riskAssessment->title,
                'category' => $riskAssessment->category,
                'version' => $riskAssessment->version + 1,
                'supersedes_id' => $riskAssessment->id,
                'description' => $riskAssessment->description,
                'likelihood' => $riskAssessment->likelihood,
                'severity' => $riskAssessment->severity,
                'control_measures' => $riskAssessment->control_measures,
                'review_date' => $riskAssessment->review_date,
                'status' => 'draft',
                'is_current' => true,
                'created_by' => $request->user()->id,
            ]);
        });

        return back()->with('success', "Version {$next->version} created as a draft.");
    }

    public function update(Request $request, RiskAssessment $riskAssessment)
    {
        $data = $this->validated($request);
        $riskAssessment->update([
            ...$data,
            // Editing a signed assessment invalidates the old approval. It
            // must be signed off again before it is active.
            'status' => 'draft',
            'signed_off_by' => null,
            'signed_off_at' => null,
        ]);

        return back()->with('success', 'Risk assessment saved.');
    }

    public function signOff(Request $request, RiskAssessment $riskAssessment)
    {
        abort_unless($riskAssessment->is_current && $riskAssessment->status === 'draft', 422, 'Only the current draft can be signed off.');

        $riskAssessment->update([
            'signed_off_by' => $request->user()->id,
            'signed_off_at' => now(),
            'status' => 'active',
        ]);

        return back()->with('success', 'Signed off.');
    }

    public function destroy(RiskAssessment $riskAssessment)
    {
        abort_if($riskAssessment->signed_off_at !== null || $riskAssessment->status !== 'draft', 422, 'Only unsigned draft risk assessments can be deleted.');
        abort_if($riskAssessment->versions()->exists(), 422, 'This assessment has version history and must be retained.');

        \Illuminate\Support\Facades\DB::transaction(function () use ($riskAssessment) {
            $previous = $riskAssessment->supersedes;
            $riskAssessment->delete();

            if ($previous) {
                $previous->update([
                    'is_current' => true,
                    'status' => $previous->signed_off_at ? 'active' : 'draft',
                ]);
            }
        });

        return back()->with('success', 'Draft risk assessment deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'category' => ['sometimes', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'likelihood' => ['required', 'integer', 'between:1,5'],
            'severity' => ['required', 'integer', 'between:1,5'],
            'control_measures' => ['nullable', 'string'],
            'review_date' => ['nullable', 'date'],
            'status' => ['sometimes', 'in:draft,active,archived'],
        ]);
    }
}
