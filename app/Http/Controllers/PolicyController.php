<?php

namespace App\Http\Controllers;

use App\Models\Policy;
use App\Support\HtmlSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class PolicyController extends Controller
{
    public function index()
    {
        return Inertia::render('Policies/Index', [
            'policies' => Policy::with(['author:id,name'])->orderBy('title')->get()->map(fn ($p) => [
                'id' => $p->id,
                'title' => $p->title,
                'category' => $p->category ?? 'General',
                'version' => $p->version,
                'review_date' => $p->review_date?->toDateString(),
                'status' => $p->status,
                'author' => $p->author->name,
                'approved' => $p->approved_at !== null,
                'updated_at' => $p->updated_at->toDateString(),
            ]),
            'canManage' => Gate::allows('manage_policies'),
        ]);
    }

    public function show(Policy $policy)
    {
        return Inertia::render('Policies/Show', [
            'policy' => [
                'id' => $policy->id,
                'title' => $policy->title,
                'body' => $policy->body,
                'version' => $policy->version,
                'review_date' => $policy->review_date?->toDateString(),
                'status' => $policy->status,
                'updated_at' => $policy->updated_at->toDateString(),
            ],
            'canManage' => Gate::allows('manage_policies'),
        ]);
    }

    public function store(Request $request)
    {
        $policy = Policy::create([
            ...$this->validated($request),
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('policies.show', $policy)->with('success', 'Policy created.');
    }

    public function update(Request $request, Policy $policy)
    {
        $policy->update($this->validated($request));

        return back()->with('success', 'Policy saved.');
    }

    public function approve(Request $request, Policy $policy)
    {
        $policy->update([
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'status' => 'active',
        ]);

        return back()->with('success', 'Policy approved.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'category' => ['nullable', 'string', 'max:100'],
            'body' => ['required', 'string'],
            'version' => ['required', 'string', 'max:20'],
            'review_date' => ['nullable', 'date'],
            'status' => ['required', 'in:draft,active,archived'],
        ]);

        $data['body'] = HtmlSanitizer::clean($data['body']);

        return $data;
    }
}
