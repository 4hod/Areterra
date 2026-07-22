<?php

namespace App\Http\Controllers;

use App\Models\FormDefinition;
use App\Models\FormSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;

class FormController extends Controller
{
    public function index(Request $request)
    {
        $canBuild = Gate::allows('build_forms');

        return Inertia::render('Forms/Index', [
            'forms' => FormDefinition::withCount('submissions')
                ->when(! $canBuild, fn ($q) => $q->where('is_active', true))
                ->orderBy('title')->get()
                ->map(fn ($f) => [
                    'id' => $f->id,
                    'title' => $f->title,
                    'slug' => $f->slug,
                    'description' => $f->description,
                    'is_active' => $f->is_active,
                    'submissions_count' => $f->submissions_count,
                ]),
            'canBuild' => $canBuild,
        ]);
    }

    public function create()
    {
        Gate::authorize('build_forms');

        return Inertia::render('Forms/Builder');
    }

    public function store(Request $request)
    {
        Gate::authorize('build_forms');

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'fields' => ['required', 'array', 'min:1'],
            'fields.*.label' => ['required', 'string', 'max:200'],
            'fields.*.type' => ['required', 'in:text,textarea,select,checkbox,date,number'],
            'fields.*.options' => ['nullable', 'array'],
            'fields.*.is_required' => ['boolean'],
        ]);

        $form = FormDefinition::create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'slug' => Str::slug($data['title']).'-'.Str::random(4),
            'is_active' => true,
            'created_by' => $request->user()->id,
        ]);

        foreach ($data['fields'] as $i => $field) {
            $form->fields()->create([
                'label' => $field['label'],
                'type' => $field['type'],
                'options' => $field['options'] ?? null,
                'is_required' => $field['is_required'] ?? false,
                'sort_order' => $i,
            ]);
        }

        return redirect('/forms')->with('success', 'Form created.');
    }

    public function show(string $slug)
    {
        $form = FormDefinition::where('slug', $slug)->with('fields')->firstOrFail();

        abort_unless($form->is_active || Gate::allows('build_forms'), 404);

        return Inertia::render('Forms/Show', [
            'form' => [
                'id' => $form->id,
                'title' => $form->title,
                'slug' => $form->slug,
                'description' => $form->description,
                'fields' => $form->fields->map(fn ($f) => [
                    'id' => $f->id,
                    'label' => $f->label,
                    'type' => $f->type,
                    'options' => $f->options,
                    'is_required' => $f->is_required,
                ]),
            ],
        ]);
    }

    public function submit(Request $request, string $slug)
    {
        $form = FormDefinition::where('slug', $slug)->with('fields')->firstOrFail();

        $rules = [];
        foreach ($form->fields as $field) {
            $rules["field_{$field->id}"] = [$field->is_required ? 'required' : 'nullable', 'string'];
        }
        $data = $request->validate($rules);

        $submission = FormSubmission::create([
            'form_id' => $form->id,
            'submitted_by' => $request->user()->id,
            'submitted_at' => now(),
        ]);

        foreach ($form->fields as $field) {
            $submission->data()->create([
                'field_id' => $field->id,
                'value' => $data["field_{$field->id}"] ?? null,
            ]);
        }

        return redirect('/forms')->with('success', 'Submitted — thank you.');
    }

    public function submissions(string $slug)
    {
        Gate::authorize('build_forms');

        $form = FormDefinition::where('slug', $slug)->with('fields')->firstOrFail();

        $submissions = $form->submissions()->with(['submittedBy:id,name', 'data'])->orderByDesc('submitted_at')->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'submitted_by' => $s->submittedBy->name,
                'submitted_at' => $s->submitted_at->toDateTimeString(),
                'answers' => $s->data->mapWithKeys(fn ($d) => [$d->field_id => $d->value]),
            ]);

        return Inertia::render('Forms/Submissions', [
            'form' => ['title' => $form->title, 'fields' => $form->fields->map(fn ($f) => ['id' => $f->id, 'label' => $f->label])],
            'submissions' => $submissions,
        ]);
    }

    public function toggleActive(FormDefinition $form)
    {
        Gate::authorize('build_forms');

        $form->update(['is_active' => ! $form->is_active]);

        return back()->with('success', $form->is_active ? 'Form activated.' : 'Form deactivated.');
    }
}
