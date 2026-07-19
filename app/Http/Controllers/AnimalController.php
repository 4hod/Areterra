<?php

namespace App\Http\Controllers;

use App\Models\Animal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class AnimalController extends Controller
{
    public function index()
    {
        $today = today();

        $animals = Animal::active()->orderBy('species')->orderBy('name')->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'name' => $a->name,
                'species' => $a->species,
                'welfare_status' => $a->welfare_status,
                'photo_path' => $a->photo_path,
                'checked_today' => $a->welfareChecks()->whereDate('created_at', $today)->exists(),
            ]);

        return Inertia::render('Animals/Index', [
            'species' => Animal::SPECIES,
            'bySpecies' => $animals->groupBy('species'),
            'canEdit' => Gate::allows('edit_animals'),
        ]);
    }

    public function show(Animal $animal)
    {
        return Inertia::render('Animals/Show', [
            'animal' => $animal->only([
                'id', 'name', 'species', 'dob', 'microchip', 'sex', 'breed',
                'photo_path', 'status', 'welfare_status',
            ]),
            'welfareChecks' => $animal->welfareChecks()->with('user:id,name')
                ->orderByDesc('created_at')->limit(20)->get(),
            'monitoring' => $animal->dailyMonitoring()->orderByDesc('monitor_date')->limit(20)->get(),
            'vetRecords' => $animal->vetRecords()->orderByDesc('visit_date')->limit(20)->get(),
            'todayMonitoring' => $animal->dailyMonitoring()->whereDate('monitor_date', today())->first(),
            'canEdit' => Gate::allows('edit_animals'),
        ]);
    }

    public function store(Request $request)
    {
        $animal = Animal::create($this->validated($request));

        return redirect()->route('animals.show', $animal)->with('success', 'Animal added.');
    }

    public function update(Request $request, Animal $animal)
    {
        $animal->update($this->validated($request));

        return back()->with('success', 'Animal updated.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'species' => ['required', 'in:'.implode(',', Animal::SPECIES)],
            'dob' => ['nullable', 'date'],
            'microchip' => ['nullable', 'string', 'max:50'],
            'sex' => ['nullable', 'in:male,female,unknown'],
            'breed' => ['nullable', 'string', 'max:100'],
            'status' => ['sometimes', 'in:active,inactive,deceased,rehomed'],
        ]);
    }
}
