<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\EndOfDayRecord;
use App\Models\LegacyImportRow;
use App\Models\Member;
use App\Models\TransportRun;
use App\Models\User;
use App\Support\XlsxTabularReader;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;
use ZipArchive;

class LegacyJotformArchiveImporter
{
    private const MEMBERS = [
        'AB' => ['Amy', 'Buckle'],
        'AT' => ['Andrew', 'Toomey'],
        'CG' => ['Colin', 'Gibbons'],
        'GW' => ['Gareth', 'Warner'],
        'ME' => ['Matthew', 'England'],
        'MW' => ['Michelle', 'Walker'],
        'NT' => ['Nicholas', 'Thomas'],
        'WG' => ['William', 'Garrett'],
    ];

    private const REQUIRED_COMMON_HEADERS = [
        '#', 'Full name', 'Submission Date', 'Date & Time - Date',
    ];

    public function __construct(private readonly XlsxTabularReader $reader) {}

    /**
     * Import all recognised spreadsheets in one transaction. Each raw source
     * row is stored encrypted and keyed by Jotform form UUID + entry number.
     *
     * @return array<string, mixed>
     */
    public function import(UploadedFile $archive, User $actor): array
    {
        $files = $this->archiveFiles($archive);
        $members = $this->members();
        $users = User::query()->get()->keyBy(fn (User $user) => $this->personKey($user->name));

        return DB::transaction(function () use ($files, $members, $users, $actor) {
            $report = [
                'files' => [],
                'source_rows' => 0,
                'created' => ['end_of_day' => 0, 'attendance' => 0, 'transport' => 0],
                'matched' => ['end_of_day' => 0, 'attendance' => 0, 'transport' => 0],
                'merged' => ['end_of_day' => 0],
                'conflicts' => ['attendance' => 0, 'transport' => 0],
                'source_flags' => ['transport_issues' => 0],
                'already_imported' => 0,
            ];

            foreach ($files as $file) {
                $rows = $this->reader->rows($file['bytes']);
                $this->validateHeaders($file, $rows);
                $fileReport = ['name' => $file['name'], 'rows' => count($rows), 'processed' => 0];

                foreach ($rows as $row) {
                    $report['source_rows']++;
                    if ($file['kind'] === 'transport' && $this->isFlagged($row['Any Issues relating to members?'] ?? null)) {
                        $report['source_flags']['transport_issues']++;
                    }
                    $result = $this->importRow($file, $row, $members, $users, $actor);

                    if ($result['outcome'] === 'already_imported') {
                        $report['already_imported']++;
                    } else {
                        foreach ($result['counts'] as $bucket => $count) {
                            if ($count > 0) {
                                $report[$bucket][$result['kind']] += $count;
                            }
                        }
                    }

                    $fileReport['processed']++;
                }

                $report['files'][] = $fileReport;
            }

            return $report;
        });
    }

    /** @return array<int, array{name: string, bytes: string, sha256: string, kind: string, form_key: string, code: string|null}> */
    private function archiveFiles(UploadedFile $archive): array
    {
        $path = $archive->getRealPath();
        if ($path === false || strtolower($archive->getClientOriginalExtension()) !== 'zip') {
            throw new RuntimeException('Upload the original ZIP archive.');
        }

        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('The uploaded archive is not a valid ZIP file.');
        }

        $files = [];
        $totalBytes = 0;

        try {
            if ($zip->numFiles > 50) {
                throw new RuntimeException('The archive contains too many files.');
            }

            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);
                if ($stat === false) {
                    continue;
                }

                $originalName = str_replace('\\', '/', (string) $stat['name']);
                if (str_ends_with($originalName, '/') || str_contains($originalName, '/__MACOSX/')) {
                    continue;
                }

                $name = basename($originalName);
                if (str_starts_with($name, '._')) {
                    continue;
                }
                if (! str_ends_with(strtolower($name), '.xlsx')) {
                    continue;
                }

                $size = (int) ($stat['size'] ?? 0);
                $totalBytes += $size;
                if ($size > 5 * 1024 * 1024 || $totalBytes > 25 * 1024 * 1024) {
                    throw new RuntimeException('The spreadsheets are larger than the safe import limit.');
                }

                $identity = $this->fileIdentity($name);
                $bytes = $zip->getFromIndex($index);
                if ($bytes === false) {
                    throw new RuntimeException("Could not read {$name} from the archive.");
                }

                $files[] = [
                    'name' => $name,
                    'bytes' => $bytes,
                    'sha256' => hash('sha256', $bytes),
                    ...$identity,
                ];
            }
        } finally {
            $zip->close();
        }

        if ($files === []) {
            throw new RuntimeException('The archive contains no recognised XLSX files.');
        }

        usort($files, fn ($a, $b) => strcmp($a['name'], $b['name']));

        return $files;
    }

    /** @return array{kind: string, form_key: string, code: string|null} */
    private function fileIdentity(string $name): array
    {
        $uuid = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';

        if (preg_match("/^(?<code>[A-Z]{2}) - End of shift report_(?<form>{$uuid})\.xlsx$/i", $name, $match)) {
            $code = strtoupper($match['code']);
            if (! isset(self::MEMBERS[$code])) {
                throw new RuntimeException("Unknown member code {$code} in {$name}.");
            }

            return ['kind' => 'end_of_day', 'form_key' => strtolower($match['form']), 'code' => $code];
        }

        if (preg_match("/^Register_(?<form>{$uuid})\.xlsx$/i", $name, $match)) {
            return ['kind' => 'attendance', 'form_key' => strtolower($match['form']), 'code' => null];
        }

        if (preg_match("/^Transport Register_(?<form>{$uuid})\.xlsx$/i", $name, $match)) {
            return ['kind' => 'transport', 'form_key' => strtolower($match['form']), 'code' => null];
        }

        throw new RuntimeException("Unrecognised spreadsheet name: {$name}");
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function validateHeaders(array $file, array $rows): void
    {
        if ($rows === []) {
            throw new RuntimeException("{$file['name']} contains no data rows.");
        }

        $headers = array_keys($rows[0]);
        $required = match ($file['kind']) {
            'end_of_day' => [...self::REQUIRED_COMMON_HEADERS, "{$file['code']} - Notes about the shift"],
            'attendance' => [...self::REQUIRED_COMMON_HEADERS, 'Who attended?'],
            'transport' => [...self::REQUIRED_COMMON_HEADERS, 'Who took Transport', 'Any Issues relating to members?'],
        };

        $missing = array_values(array_diff($required, $headers));
        if ($missing !== []) {
            throw new RuntimeException("{$file['name']} is missing columns: ".implode(', ', $missing));
        }
    }

    /** @return array{outcome: string, kind: string, target_count: int, counts?: array<string, int>} */
    private function importRow(array $file, array $row, $members, $users, User $actor): array
    {
        $entryId = trim((string) ($row['#'] ?? ''));
        if ($entryId === '') {
            throw new RuntimeException("{$file['name']} contains a row without an entry number.");
        }

        $sourceKey = "jotform:{$file['form_key']}:{$entryId}";
        $payloadJson = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $payloadHash = hash('sha256', $payloadJson);
        $existingSource = LegacyImportRow::where('source_key', $sourceKey)->first();

        if ($existingSource !== null) {
            if (! hash_equals($existingSource->payload_sha256, $payloadHash)) {
                throw new RuntimeException("Source entry {$sourceKey} has changed since its earlier import.");
            }

            return ['outcome' => 'already_imported', 'kind' => $file['kind'], 'target_count' => 0];
        }

        $recordedAt = $this->spreadsheetDate($row['Date & Time - Date'] ?? null, $file['name'], $entryId);
        $submittedAt = $this->spreadsheetDate($row['Submission Date'] ?? null, $file['name'], $entryId);
        $authorName = $this->nullableString($row['Full name'] ?? null);
        $user = $users->get($this->personKey((string) $authorName), $actor);

        $result = match ($file['kind']) {
            'end_of_day' => $this->importEndOfDay($file, $row, $members[$file['code']], $user, $recordedAt, $entryId),
            'attendance' => $this->importAttendance($row, $members, $user, $recordedAt),
            'transport' => $this->importTransport($row, $members, $user, $recordedAt),
        };

        LegacyImportRow::create([
            'source_key' => $sourceKey,
            'source_system' => 'jotform',
            'form_key' => $file['form_key'],
            'form_name' => $file['kind'],
            'entry_id' => $entryId,
            'source_file_name' => $file['name'],
            'source_file_sha256' => $file['sha256'],
            'payload_sha256' => $payloadHash,
            'submitted_at' => $submittedAt,
            'recorded_at' => $recordedAt,
            'author_name' => $authorName,
            'outcome' => $result['outcome'],
            'target_count' => $result['target_count'],
            'imported_by' => $actor->id,
            'payload' => $row,
        ]);

        return ['kind' => $file['kind'], ...$result];
    }

    /** @return array{outcome: string, target_count: int, counts: array<string, int>} */
    private function importEndOfDay(array $file, array $row, Member $member, User $user, CarbonImmutable $recordedAt, string $entryId): array
    {
        $note = trim((string) ($row["{$file['code']} - Notes about the shift"] ?? ''));
        if ($note === '') {
            throw new RuntimeException("{$file['name']} entry {$entryId} has no shift note.");
        }

        $record = EndOfDayRecord::where('member_id', $member->id)
            ->whereDate('date', $recordedAt->toDateString())
            ->first();

        if ($record === null) {
            EndOfDayRecord::create([
                'member_id' => $member->id,
                'date' => $recordedAt->toDateString(),
                'user_id' => $user->id,
                'source' => 'jotform',
                'source_author_name' => $this->nullableString($row['Full name'] ?? null),
                'notes' => $note,
            ]);

            return $this->result('created', 1);
        }

        $existing = $this->normaliseText((string) $record->notes);
        $incoming = $this->normaliseText($note);
        if ($existing === $incoming || ($incoming !== '' && str_contains($existing, $incoming))) {
            if (! $record->source_author_name && $row['Full name']) {
                $record->update(['source_author_name' => $row['Full name']]);
            }

            return $this->result('matched', 1);
        }

        $record->update([
            'notes' => rtrim((string) $record->notes)."\n\n[Imported Jotform entry {$entryId}]\n{$note}",
        ]);

        return $this->result('merged', 1);
    }

    /** @return array{outcome: string, target_count: int, counts: array<string, int>} */
    private function importAttendance(array $row, $members, User $user, CarbonImmutable $recordedAt): array
    {
        $codes = $this->memberCodes($row['Who attended?'] ?? null);
        $created = 0;
        $matched = 0;
        $conflicts = 0;

        foreach ($codes as $code) {
            $member = $members[$code];
            $attendance = Attendance::where('member_id', $member->id)
                ->whereDate('date', $recordedAt->toDateString())
                ->first();

            if ($attendance === null) {
                Attendance::create([
                    'member_id' => $member->id,
                    'date' => $recordedAt->toDateString(),
                    'status' => 'present',
                    'checked_in' => true,
                    'checked_in_at' => $recordedAt,
                    'recorded_by' => $user->id,
                ]);
                $created++;
            } elseif ($attendance->checked_in || $attendance->status === 'present') {
                $matched++;
            } elseif ($attendance->status === 'expected') {
                $attendance->update([
                    'status' => 'present',
                    'checked_in' => true,
                    'checked_in_at' => $attendance->checked_in_at ?: $recordedAt,
                    'recorded_by' => $attendance->recorded_by ?: $user->id,
                ]);
                $matched++;
            } else {
                $conflicts++;
            }
        }

        return [
            'outcome' => $conflicts > 0 ? 'conflict' : ($created > 0 ? 'created' : 'matched'),
            'target_count' => count($codes),
            'counts' => ['created' => $created, 'matched' => $matched, 'conflicts' => $conflicts],
        ];
    }

    /** @return array{outcome: string, target_count: int, counts: array<string, int>} */
    private function importTransport(array $row, $members, User $user, CarbonImmutable $recordedAt): array
    {
        $codes = $this->memberCodes($row['Who took Transport'] ?? null);
        $created = 0;
        $matched = 0;
        $conflicts = 0;

        foreach ($codes as $code) {
            $member = $members[$code];
            $run = TransportRun::where('member_id', $member->id)
                ->whereDate('run_date', $recordedAt->toDateString())
                ->where('phase', 'morning')
                ->first();

            if ($run === null) {
                TransportRun::create([
                    'run_date' => $recordedAt->toDateString(),
                    'member_id' => $member->id,
                    'phase' => 'morning',
                    'outcome' => 'collected',
                    'completed_at' => $recordedAt,
                    'user_id' => $user->id,
                ]);
                $created++;
            } elseif ($run->outcome === 'collected') {
                $matched++;
            } else {
                $conflicts++;
            }
        }

        return [
            'outcome' => $conflicts > 0 ? 'conflict' : ($created > 0 ? 'created' : 'matched'),
            'target_count' => count($codes),
            'counts' => ['created' => $created, 'matched' => $matched, 'conflicts' => $conflicts],
        ];
    }

    /** @return array<string, Member> */
    private function members(): array
    {
        $members = [];
        foreach (self::MEMBERS as $code => [$firstName, $lastName]) {
            $member = Member::withTrashed()
                ->where('first_name', $firstName)
                ->where('last_name', $lastName)
                ->first();

            if ($member === null) {
                throw new RuntimeException("Member {$code} ({$firstName} {$lastName}) does not exist in the Hub.");
            }

            $members[$code] = $member;
        }

        return $members;
    }

    /** @return array<int, string> */
    private function memberCodes(mixed $value): array
    {
        $codes = array_values(array_unique(array_filter(array_map(
            fn ($part) => strtoupper(trim($part)),
            preg_split('/[,;\r\n]+/', (string) $value) ?: [],
        ))));

        if ($codes === []) {
            throw new RuntimeException('A register row contains no member codes.');
        }

        $unknown = array_values(array_diff($codes, array_keys(self::MEMBERS)));
        if ($unknown !== []) {
            throw new RuntimeException('Unknown member codes: '.implode(', ', $unknown));
        }

        return $codes;
    }

    private function spreadsheetDate(mixed $value, string $fileName, string $entryId): CarbonImmutable
    {
        if ($value === null || trim((string) $value) === '') {
            throw new RuntimeException("{$fileName} entry {$entryId} has no date.");
        }

        try {
            if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) {
                $serial = (float) $value;
                $days = (int) floor($serial);
                $seconds = (int) round(($serial - $days) * 86400);
                $wallClock = CarbonImmutable::create(1899, 12, 30, 0, 0, 0, 'UTC')
                    ->addDays($days)
                    ->addSeconds($seconds);

                return CarbonImmutable::createFromFormat(
                    'Y-m-d H:i:s',
                    $wallClock->format('Y-m-d H:i:s'),
                    'Europe/London',
                );
            }

            return CarbonImmutable::parse((string) $value, 'Europe/London');
        } catch (Throwable) {
            throw new RuntimeException("{$fileName} entry {$entryId} has an invalid date.");
        }
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function normaliseText(string $value): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $value)));
    }

    private function personKey(string $value): string
    {
        return $this->normaliseText($value);
    }

    private function isFlagged(mixed $value): bool
    {
        $value = $this->normaliseText((string) $value);

        return $value !== '' && ! in_array($value, ['no', 'none', 'n/a'], true);
    }

    /** @return array{outcome: string, target_count: int, counts: array<string, int>} */
    private function result(string $outcome, int $targetCount): array
    {
        return [
            'outcome' => $outcome,
            'target_count' => $targetCount,
            'counts' => [$outcome => $targetCount],
        ];
    }
}
