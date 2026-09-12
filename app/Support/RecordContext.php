<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Point 2 — open a record and see everything connected to it, without jumping
 * between screens.
 *
 * Relationship names are probed rather than hard-coded, so this works for a
 * Member, an Animal or a Grant without a bespoke method for each, and doesn't
 * break when a model doesn't have one of them.
 */
final class RecordContext
{
    private const RELATIONS = [
        'attendances', 'sessions', 'activities', 'outcomes', 'incidents',
        'welfareChecks', 'vetRecords', 'transportRuns', 'invoices',
        'expenditures', 'communications', 'documents', 'tasks', 'notes',
        'reviews', 'consents', 'alerts',
    ];

    /** @return array<string, mixed> */
    public static function for(Model $record, int $perRelation = 10): array
    {
        $context = [];

        foreach (self::RELATIONS as $relation) {
            if (! method_exists($record, $relation)) {
                continue;
            }

            try {
                $context[$relation] = $record->{$relation}()->latest()->limit($perRelation)->get();
            } catch (\Throwable) {
                // A relation that needs arguments, or isn't a relation at all.
                continue;
            }
        }

        return [
            'record' => $record,
            'related' => $context,
            'timeline' => Timeline::forRecord($record, 25),
            'open_tasks' => method_exists($record, 'openTasks') ? $record->openTasks()->get() : [],
        ];
    }
}
