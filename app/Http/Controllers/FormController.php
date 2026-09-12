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

    public function show(Request $request, string $slug)
    {
        $form = FormDefinition::where('slug', $slug)->with('fields')->firstOrFail();

        abort_unless($form->is_active || Gate::allows('build_forms'), 404);

        // A report can be opened about a specific record:
        //   /forms/welfare-concern?about=animal&id=4
        $subject = $this->resolveSubject($request->query('about'), $request->query('id'));

        return Inertia::render('Forms/Show', [
            'subject' => $subject ? [
                'type' => $request->query('about'),
                'id' => $subject->getKey(),
                'name' => $subject->name
                    ?? (method_exists($subject, 'displayName') ? $subject->displayName() : null)
                    ?? $subject->title,
            ] : null,
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
        $rules['about'] = ['nullable', 'string'];
        $rules['about_id'] = ['nullable', 'integer'];
        $data = $request->validate($rules);

        $subject = $this->resolveSubject($data['about'] ?? null, $data['about_id'] ?? null);

        $submission = FormSubmission::create([
            'form_id' => $form->id,
            'submitted_by' => $request->user()->id,
            'submitted_at' => now(),
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
        ]);

        foreach ($form->fields as $field) {
            $submission->data()->create([
                'field_id' => $field->id,
                'value' => $data["field_{$field->id}"] ?? null,
            ]);
        }

        // Straight back to the record, where the report now appears.
        if ($subject) {
            $back = match ($data['about']) {
                'member' => "/members/{$subject->getKey()}",
                'animal' => "/animals/{$subject->getKey()}",
                default => '/forms',
            };

            return redirect($back)->with('success', "{$form->title} saved to this record.");
        }

        return redirect('/forms')->with('success', 'Submitted — thank you.');
    }

    /** Maps the ?about= shorthand to a real record. Anything unknown is ignored. */
    private function resolveSubject(?string $about, $id)
    {
        if (! $about || ! $id) {
            return null;
        }

        $model = match ($about) {
            'member' => \App\Models\Member::class,
            'animal' => \App\Models\Animal::class,
            'vehicle' => \App\Models\Vehicle::class,
            'grant' => \App\Models\Grant::class,
            default => null,
        };

        return $model ? $model::find($id) : null;
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
