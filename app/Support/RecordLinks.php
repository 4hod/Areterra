<?php

namespace App\Support;

use App\Models\Activity;
use App\Models\Animal;
use App\Models\Grant;
use App\Models\Incident;
use App\Models\Member;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class RecordLinks
{
    private const TYPES = [
        'member' => [Member::class, 'view_member_details'],
        'animal' => [Animal::class, 'view_animals'],
        'vehicle' => [Vehicle::class, 'view_vehicles'],
        'activity' => [Activity::class, null],
        'grant' => [Grant::class, 'manage_finance'],
        'incident' => [Incident::class, 'view_all_incidents'],
    ];

    public static function resolve(?string $type, $id): ?Model
    {
        if (! $type || ! $id || ! isset(self::TYPES[$type])) {
            return null;
        }

        [$model, $capability] = self::TYPES[$type];
        if ($capability) {
            Gate::authorize($capability);
        }

        return $model::findOrFail($id);
    }

    public static function options(array $types): array
    {
        return collect($types)->filter(fn ($type) => isset(self::TYPES[$type]))
            ->filter(function ($type) {
                $capability = self::TYPES[$type][1];

                return ! $capability || Gate::allows($capability);
            })
            ->mapWithKeys(function ($type) {
                $model = self::TYPES[$type][0];
                $query = $model::query();
                if (in_array($type, ['member', 'animal'], true)) {
                    $query->where('status', 'active');
                }
                if ($type === 'vehicle') {
                    $query->where('active', true);
                }
                if ($type === 'activity') {
                    $query->whereDate('activity_date', '>=', today()->subMonths(3));
                }

                return [$type => $query->limit(250)->get()->map(fn (Model $record) => [
                    'id' => $record->getKey(),
                    'name' => self::name($record),
                ])->sortBy('name')->values()];
            })->all();
    }

    public static function metadata(Model $record): array
    {
        $type = self::type($record);

        return [
            'id' => $record->getKey(),
            'type' => $type,
            'name' => self::name($record),
            'url' => self::url($record),
        ];
    }

    public static function metadataIfVisible(Model $record): ?array
    {
        return self::isVisible($record) ? self::metadata($record) : null;
    }

    public static function isVisible(Model $record): bool
    {
        if ($record instanceof Incident && ! Gate::allows('view_all_incidents')) {
            return auth()->check() && (int) $record->reported_by === (int) auth()->id();
        }

        $type = self::type($record);
        $capability = self::TYPES[$type][1] ?? null;

        return ! $capability || Gate::allows($capability);
    }

    public static function type(Model $record): string
    {
        foreach (self::TYPES as $type => [$class]) {
            if ($record instanceof $class) {
                return $type;
            }
        }

        return strtolower(class_basename($record));
    }

    private static function name(Model $record): string
    {
        return match (true) {
            $record instanceof Member => $record->displayName(),
            $record instanceof Vehicle => trim($record->registration.' '.($record->make_model ?? '')),
            $record instanceof Animal => $record->name.' · '.$record->species,
            $record instanceof Activity => $record->title.' · '.$record->activity_date?->format('j M Y'),
            default => $record->name ?? $record->title ?? '#'.$record->getKey(),
        };
    }

    private static function url(Model $record): ?string
    {
        return match (true) {
            $record instanceof Member => route('members.show', $record, false),
            $record instanceof Animal => route('animals.show', $record, false),
            $record instanceof Vehicle => route('vehicles', [], false).'#vehicle-'.$record->getKey(),
            $record instanceof Activity => route('calendar', [], false).'?date='.$record->activity_date?->toDateString(),
            $record instanceof Grant => route('finance', [], false),
            $record instanceof Incident => route('incidents', [], false).'?about=incident&id='.$record->getKey(),
            default => null,
        };
    }
}
