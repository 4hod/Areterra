<?php

namespace App\Http\Controllers;

use App\Events\AnimalHealthConcernRaised;

use App\Models\Animal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WelfareCheckController extends Controller
{
    public function store(Request $request, Animal $animal)
    {
        $data = $request->validate([
            'status' => ['required', 'in:green,amber,red'],
            'notes' => ['nullable', 'string'],
            'concern' => ['boolean'],
            'fed' => ['required', 'boolean'],
            'pellets_given' => ['sometimes', 'accepted'],
            'greenery_given' => ['sometimes', 'accepted'],
            'fresh_water' => ['sometimes', 'accepted'],
            'hay_topped_up' => ['sometimes', 'accepted'],
            'enclosure_cleaned' => ['sometimes', 'boolean'],
            'treats_given' => ['required', 'boolean'],
            'treats_notes' => ['nullable', 'string'],
        ]);

        $this->record($animal, $data, $request->user()->id);

        return back()->with('success', "Welfare check logged for {$animal->name}.");
    }

    // One enclosure-care round per species, with a quick result for every
    // animal in that enclosure and explicit exceptions where needed.
    public function storeSpecies(Request $request)
    {
        $data = $request->validate([
            'species' => ['required', 'in:'.implode(',', Animal::SPECIES)],
            'verification_confirmed' => ['accepted'],
            'pellets_given' => ['required', 'accepted'],
            'greenery_given' => ['required', 'accepted'],
            'fresh_water' => ['required', 'accepted'],
            'hay_topped_up' => ['required', 'accepted'],
            'enclosure_cleaned' => ['required', 'boolean'],
            'checks' => ['required', 'array'],
            'checks.*.animal_id' => ['required', 'exists:animals,id'],
            'checks.*.status' => ['nullable', 'in:amber,red'],
            'checks.*.notes' => ['nullable', 'string'],
            'checks.*.fed' => ['required', 'boolean'],
            'checks.*.treats_given' => ['required', 'boolean'],
            'checks.*.treats_notes' => ['nullable', 'string'],
        ]);

        $animals = Animal::active()->where('species', $data['species'])->get();
        $submittedIds = collect($data['checks'])->pluck('animal_id')->unique()->sort()->values();
        $expectedIds = $animals->pluck('id')->sort()->values();

        if ($submittedIds->all() !== $expectedIds->all()) {
            throw ValidationException::withMessages([
                'checks' => 'Every active animal in this species must be reviewed before saving.',
            ]);
        }

        $lastClean = \App\Models\WelfareCheck::query()
            ->where('enclosure_cleaned', true)
            ->whereHas('animal', fn ($query) => $query->where('species', $data['species']))
            ->latest('created_at')
            ->value('created_at');
        $cleanDue = ! $lastClean || \Illuminate\Support\Carbon::parse($lastClean)->lte(now()->subDays(7));

        if ($cleanDue && ! $data['enclosure_cleaned']) {
            throw ValidationException::withMessages([
                'enclosure_cleaned' => "The {$data['species']} enclosure is due its weekly full clean.",
            ]);
        }

        $overrides = collect($data['checks'])->keyBy('animal_id');

        DB::transaction(function () use ($animals, $overrides, $data, $request) {
            $animals->each(function (Animal $animal) use ($overrides, $data, $request) {
                    $o = $overrides->get($animal->id);
                    $hasConcern = ! empty($o['status']);

                    $this->record($animal, [
                        'status' => $hasConcern ? $o['status'] : 'green',
                        'notes' => $o['notes'] ?? null,
                        'concern' => $hasConcern,
                        'fed' => $o['fed'],
                        'pellets_given' => $data['pellets_given'],
                        'greenery_given' => $data['greenery_given'],
                        'fresh_water' => $data['fresh_water'],
                        'hay_topped_up' => $data['hay_topped_up'],
                        'enclosure_cleaned' => $data['enclosure_cleaned'],
                        'treats_given' => $o['treats_given'],
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
            'pellets_given' => $data['pellets_given'] ?? false,
            'greenery_given' => $data['greenery_given'] ?? false,
            'fresh_water' => $data['fresh_water'] ?? false,
            'hay_topped_up' => $data['hay_topped_up'] ?? false,
            'enclosure_cleaned' => $data['enclosure_cleaned'] ?? false,
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
            $recipients = \App\Models\User::whereKeyNot($userId)
                ->whereHas('capabilityGrants', fn ($query) => $query->where('capability', 'edit_animals'))->get();

            if ($recipients->isNotEmpty()) {
                \Illuminate\Support\Facades\Notification::send(
                    $recipients,
                new \App\Notifications\ConcernRaised(
                    "Welfare: {$animal->name}",
                    "{$animal->species} {$animal->name} — ".implode('; ', $alerts).'.',
                    "/animals/{$animal->id}",
                ),
                );
            }
        }
    }
}
