<?php

namespace App\Support;

use App\Models\Animal;
use Illuminate\Support\Collection;

class AnimalWelfareBaseline
{
    public static function for(Animal $animal): array
    {
        $records = $animal->dailyMonitoring()
            ->orderByDesc('monitor_date')
            ->limit(31)
            ->get();

        $latest = $records->first();
        $history = $records->slice(1)->take(30)->values();
        $signals = [];

        if (! $latest) {
            return self::result('insufficient', [], 0, null);
        }

        self::numericSignal($signals, 'Weight', $latest->weight_grams, $history->pluck('weight_grams'), '%', 10, 20);
        self::numericSignal($signals, 'Body condition', $latest->body_condition, $history->pluck('body_condition'), ' points', 1, 2, false);

        foreach (['appetite' => 'Appetite', 'droppings' => 'Droppings', 'behaviour' => 'Behaviour'] as $field => $label) {
            self::categorySignal($signals, $label, $latest->{$field}, $history->pluck($field));
        }

        if ($latest->concern) {
            $signals[] = ['level' => 'red', 'label' => 'Concern recorded', 'explanation' => 'The latest monitoring entry was explicitly marked as a concern.'];
        }

        $level = collect($signals)->contains(fn ($s) => $s['level'] === 'red')
            ? 'red'
            : (collect($signals)->contains(fn ($s) => $s['level'] === 'amber') ? 'amber' : 'green');

        return self::result($history->count() >= 3 ? $level : 'insufficient', $signals, $history->count(), $latest->monitor_date?->toDateString());
    }

    private static function numericSignal(array &$signals, string $label, mixed $latest, Collection $values, string $unit, float $amber, float $red, bool $percentage = true): void
    {
        $values = $values->filter(fn ($value) => $value !== null)->map(fn ($value) => (float) $value)->values();
        if ($latest === null || $values->count() < 3) return;

        $sorted = $values->sort()->values();
        $middle = intdiv($sorted->count(), 2);
        $median = $sorted->count() % 2 ? $sorted[$middle] : ($sorted[$middle - 1] + $sorted[$middle]) / 2;
        if ($median <= 0) return;

        $change = $percentage ? abs(((float) $latest - $median) / $median) * 100 : abs((float) $latest - $median);
        $level = $change >= $red ? 'red' : ($change >= $amber ? 'amber' : 'green');
        $displayChange = round($change, 1);
        $baseline = round($median, 1);
        $signals[] = [
            'level' => $level,
            'label' => $label,
            'explanation' => $level === 'green'
                ? "Latest value is within the recent baseline (median {$baseline})."
                : "Latest value differs from the recent median of {$baseline} by {$displayChange}{$unit}.",
        ];
    }

    private static function categorySignal(array &$signals, string $label, mixed $latest, Collection $values): void
    {
        $normalise = fn ($value) => mb_strtolower(trim((string) $value));
        $values = $values->filter()->map($normalise)->filter()->values();
        $latest = $normalise($latest);
        if ($latest === '' || $values->count() < 3) return;

        $counts = $values->countBy()->sortDesc();
        $usual = (string) $counts->keys()->first();
        $confidence = ((int) $counts->first()) / $values->count();
        $unusual = $confidence >= .6 && $latest !== $usual;

        $signals[] = [
            'level' => $unusual ? 'amber' : 'green',
            'label' => $label,
            'explanation' => $unusual
                ? "Latest entry is ‘{$latest}’; the usual recent entry is ‘{$usual}’."
                : "Latest entry matches the recent pattern (‘{$usual}’).",
        ];
    }

    private static function result(string $level, array $signals, int $observations, ?string $latestDate): array
    {
        return [
            'level' => $level,
            'observations' => $observations,
            'latest_date' => $latestDate,
            'signals' => $signals,
            'disclaimer' => 'Pattern support only — staff judgement and veterinary advice always take priority.',
        ];
    }
}
