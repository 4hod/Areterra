<?php

namespace App\Http\Controllers;

use App\Models\Animal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WelfareCheckController extends Controller
{
    public function store(Request $request, Animal $animal)
    {
        $data = $request->validate([
            'status' => ['required', 'in:green,amber,red'],
            'notes' => ['nullable', 'string'],
            'concern' => ['boolean'],
        ]);

        $this->record($animal, $data, $request->user()->id);

        return back()->with('success', "Welfare check logged for {$animal->name}.");
    }

    // Species group check: everyone healthy, except any animals explicitly flagged.
    public function storeSpecies(Request $request)
    {
        $data = $request->validate([
            'species' => ['required', 'in:'.implode(',', Animal::SPECIES)],
            'flagged' => ['nullable', 'array'],
            'flagged.*.animal_id' => ['required', 'exists:animals,id'],
            'flagged.*.status' => ['required', 'in:amber,red'],
            'flagged.*.notes' => ['nullable', 'string'],
        ]);

        $flagged = collect($data['flagged'] ?? [])->keyBy('animal_id');

        DB::transaction(function () use ($data, $flagged, $request) {
            Animal::active()->where('species', $data['species'])->get()
                ->each(function (Animal $animal) use ($flagged, $request) {
                    $flag = $flagged->get($animal->id);
                    $this->record($animal, [
                        'status' => $flag['status'] ?? 'green',
                        'notes' => $flag['notes'] ?? null,
                        'concern' => $flag !== null,
                    ], $request->user()->id);
                });
        });

        return back()->with('success', "{$data['species']} welfare checks logged.");
    }

    private function record(Animal $animal, array $data, int $userId): void
    {
        $animal->welfareChecks()->create([
            'user_id' => $userId,
            'status' => $data['status'],
            'notes' => $data['notes'] ?? null,
            'concern' => $data['concern'] ?? false,
        ]);

        $animal->update(['welfare_status' => $data['status']]);

        if ($data['concern'] ?? false) {
            \Illuminate\Support\Facades\Notification::send(
                \App\Models\User::managers()->get(),
                new \App\Notifications\ConcernRaised(
                    "Welfare concern: {$animal->name}",
                    trim("{$animal->species} {$animal->name} flagged {$data['status']}. ".($data['notes'] ?? '')),
                    "/animals/{$animal->id}",
                ),
            );
        }
    }
}
