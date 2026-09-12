<?php

namespace App\Http\Controllers;

use App\Events\AnimalHealthConcernRaised;

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
            'fed' => ['boolean'],
            'treats_given' => ['boolean'],
            'treats_notes' => ['nullable', 'string'],
        ]);

        $this->record($animal, $data, $request->user()->id);

        return back()->with('success', "Welfare check logged for {$animal->name}.");
    }

    // Species group check: everyone healthy and fed by default, except
    // whatever's explicitly noted per animal (concern, not fed, treats given).
    public function storeSpecies(Request $request)
    {
        $data = $request->validate([
            'species' => ['required', 'in:'.implode(',', Animal::SPECIES)],
            'checks' => ['nullable', 'array'],
            'checks.*.animal_id' => ['required', 'exists:animals,id'],
            'checks.*.status' => ['nullable', 'in:amber,red'],
            'checks.*.notes' => ['nullable', 'string'],
            'checks.*.fed' => ['boolean'],
            'checks.*.treats_given' => ['boolean'],
            'checks.*.treats_notes' => ['nullable', 'string'],
        ]);

        $overrides = collect($data['checks'] ?? [])->keyBy('animal_id');

        DB::transaction(function () use ($data, $overrides, $request) {
            Animal::active()->where('species', $data['species'])->get()
                ->each(function (Animal $animal) use ($overrides, $request) {
                    $o = $overrides->get($animal->id);
                    $hasConcern = $o && ! empty($o['status']);

                    $this->record($animal, [
                        'status' => $hasConcern ? $o['status'] : 'green',
                        'notes' => $o['notes'] ?? null,
                        'concern' => $hasConcern,
                        'fed' => $o['fed'] ?? true,
                        'treats_given' => $o['treats_given'] ?? false,
                        'treats_notes' => $o['treats_notes'] ?? null,
                    ], $request->user()->id);
                });
        });

        return back()->with('success', "{$data['species']} welfare checks logged.");
    }

    private function record(Animal $animal, array $data, int $userId): void
    {
        $check = $animal->welfareChecks()->create([
            'user_id' => $userId,
            'status' => $data['status'],
            'notes' => $data['notes'] ?? null,
            'concern' => $data['concern'] ?? false,
            'fed' => $data['fed'] ?? true,
            'treats_given' => $data['treats_given'] ?? false,
            'treats_notes' => $data['treats_notes'] ?? null,
        ]);

        $animal->update(['welfare_status' => $data['status']]);

        // Raises the vet follow-up task. Previously this event existed and had a
        // listener, but nothing ever fired it.
        if ($data['concern'] ?? false) {
            AnimalHealthConcernRaised::dispatch($check);
        }

        $alerts = [];
        if ($data['concern'] ?? false) {
            $alerts[] = "flagged {$data['status']}".(($data['notes'] ?? null) ? ": {$data['notes']}" : '');
        }
        if (! ($data['fed'] ?? true)) {
            $alerts[] = 'not fed today';
        }

        if ($alerts) {
            \Illuminate\Support\Facades\Notification::send(
                \App\Models\User::managers()->get(),
                new \App\Notifications\ConcernRaised(
                    "Welfare: {$animal->name}",
                    "{$animal->species} {$animal->name} — ".implode('; ', $alerts).'.',
                    "/animals/{$animal->id}",
                ),
            );
        }
    }
}
