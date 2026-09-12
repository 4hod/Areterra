<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Points 2 and 9 — "what happened on 4 September?" and "what has changed for
 * this member since their last review?"
 *
 * Reads the existing audit_log; adds no new storage. Encrypted values were
 * already redacted at write time by AuditLogObserver, so nothing sensitive
 * surfaces here that wasn't already safe to show.
 */
final class Timeline
{
    /** Everything that happened to one record, newest first. */
    public static function forRecord(Model $record, int $limit = 50): array
    {
        return AuditLog::query()
            ->with('user')
            ->where('subject_type', $record->getMorphClass())
            ->where('subject_id', $record->getKey())
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (AuditLog $row) => self::entry($row))
            ->all();
    }

    /** Everything that happened across the whole Hub on one day. */
    public static function forDate(string $date, int $limit = 200): array
    {
        return AuditLog::query()
            ->with('user')
            ->whereDate('created_at', $date)
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (AuditLog $row) => self::entry($row, withSubject: true))
            ->all();
    }

    /** Changes to a record since a given moment — for review paperwork. */
    public static function forRecordSince(Model $record, string $since): array
    {
        return AuditLog::query()
            ->with('user')
            ->where('subject_type', $record->getMorphClass())
            ->where('subject_id', $record->getKey())
            ->where('created_at', '>=', $since)
            ->latest()
            ->get()
            ->map(fn (AuditLog $row) => self::entry($row))
            ->all();
    }

    private static function entry(AuditLog $row, bool $withSubject = false): array
    {
        $fields = array_keys($row->changes ?? []);

        return [
            'id' => $row->id,
            'at' => $row->created_at?->toIso8601String(),
            'by' => $row->user?->name ?? 'System',
            'action' => $row->action,
            'subject' => $withSubject ? class_basename($row->subject_type).' #'.$row->subject_id : null,
            'fields' => $fields,
            'summary' => sprintf(
                '%s %s%s',
                $row->user?->name ?? 'System',
                $row->action,
                $fields === [] ? '' : ' ('.implode(', ', array_slice($fields, 0, 5)).')',
            ),
        ];
    }
}
