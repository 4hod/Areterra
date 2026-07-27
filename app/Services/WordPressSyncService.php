<?php

namespace App\Services;

use App\Models\EndOfDayRecord;
use App\Models\Member;
use App\Models\MemberNote;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class WordPressSyncService
{
    public function testConnection(): array
    {
        $payload = $this->unwrap($this->get($this->endpoint('sync/members'), [
            'summary' => 1,
        ]));

        return [
            'connected' => true,
            'members_available' => (int) data_get($payload, 'meta.total', 0),
            'sync_version' => (int) data_get($payload, 'meta.sync_version', 0),
        ];
    }

    public function syncMembers(): array
    {
        $remoteMembers = $this->downloadMembers();
        [$usersByName, $fallbackUserId] = $this->userLookup();

        $result = DB::transaction(function () use ($remoteMembers, $usersByName, $fallbackUserId) {
            $created = 0;
            $updated = 0;
            $notesCreated = 0;
            $notesUpdated = 0;
            $sessionsCreated = 0;
            $sessionsUpdated = 0;
            $legacySessionNotesRemoved = 0;

            $existing = Member::withTrashed()->get();
            $byWordPressId = $existing
                ->filter(fn (Member $member) => $member->wordpress_id !== null)
                ->keyBy(fn (Member $member) => (string) $member->wordpress_id);
            $byName = $existing->keyBy(fn (Member $member) => $this->nameKey(
                $member->first_name,
                $member->last_name,
            ));

            foreach ($remoteMembers as $remote) {
                $wordpressId = (int) $remote['member']['id'];
                $nameKey = $this->nameKey(
                    (string) $remote['member']['first_name'],
                    (string) $remote['member']['last_name'],
                );

                /** @var Member|null $member */
                $member = $byWordPressId->get((string) $wordpressId) ?? $byName->get($nameKey);
                $isNew = $member === null;
                $member ??= new Member();

                $payload = $this->memberPayload($remote['member'], $isNew);
                $member->fill($payload);

                if ($member->trashed() && ($payload['status'] ?? 'active') === 'active') {
                    $member->restore();
                }

                if ($isNew || $member->isDirty()) {
                    $member->save();
                    $isNew ? $created++ : $updated++;
                }

                $member->settings()->firstOrCreate([], [
                    'attendance_days' => [],
                    'transport_required' => false,
                ]);

                $byWordPressId->put((string) $wordpressId, $member);
                $byName->put($nameKey, $member);

                foreach ($remote['notes'] as $remoteNote) {
                    $change = $this->syncMemberNote($member, $remoteNote);
                    $notesCreated += $change === 'created' ? 1 : 0;
                    $notesUpdated += $change === 'updated' ? 1 : 0;
                }

                foreach ($remote['session_notes'] as $remoteSession) {
                    $change = $this->syncEndOfDayRecord(
                        $member,
                        $remoteSession,
                        $usersByName,
                        $fallbackUserId,
                    );
                    $sessionsCreated += $change === 'created' ? 1 : 0;
                    $sessionsUpdated += $change === 'updated' ? 1 : 0;
                }

                // Stage 1.1 displayed WordPress handovers in a separate imported
                // notes card. They are now normal end-of-day records, so remove
                // those legacy display rows after the native record is present.
                $legacySessionNotesRemoved += MemberNote::where('member_id', $member->id)
                    ->where('source', 'wordpress')
                    ->where('note_type', 'end_of_day')
                    ->delete();
            }

            return [
                'members_received' => count($remoteMembers),
                'members_created' => $created,
                'members_updated' => $updated,
                'notes_created' => $notesCreated,
                'notes_updated' => $notesUpdated,
                'sessions_created' => $sessionsCreated,
                'sessions_updated' => $sessionsUpdated,
                'legacy_session_notes_removed' => $legacySessionNotesRemoved,
            ];
        });

        Setting::set('wordpress_last_sync_at', now()->toIso8601String());
        Setting::set('wordpress_last_sync_summary', json_encode($result, JSON_THROW_ON_ERROR));

        return $result;
    }

    private function downloadMembers(): array
    {
        $response = $this->get($this->endpoint('sync/members'));

        if ($response->status() === 404) {
            throw new RuntimeException(
                'The WordPress bulk-sync endpoint is not installed. Upload and activate the latest Areterra Hub WordPress plugin, then test the connection again.'
            );
        }

        $payload = $this->unwrap($response);
        $records = $payload['data'];

        if (! is_array($records)) {
            throw new RuntimeException('WordPress returned an invalid bulk member export.');
        }

        $members = [];

        foreach ($records as $record) {
            if (! is_array($record) || ! is_array($record['member'] ?? null)) {
                continue;
            }

            $memberId = (int) data_get($record, 'member.id', 0);
            if ($memberId < 1) {
                continue;
            }

            $members[] = [
                'member' => $record['member'],
                'notes' => $this->normaliseMemberNotes(
                    is_array($record['notes'] ?? null) ? $record['notes'] : [],
                ),
                'session_notes' => $this->normaliseSessionNotes(
                    is_array($record['session_notes'] ?? null) ? $record['session_notes'] : [],
                ),
            ];
        }

        return $members;
    }

    private function normaliseMemberNotes(array $notes): array
    {
        $normalised = [];

        foreach ($notes as $note) {
            $id = (int) ($note['id'] ?? 0);
            if ($id < 1) {
                continue;
            }

            $normalised[] = [
                'source_key' => "member-note:{$id}",
                'legacy_wordpress_note_id' => $id,
                'note_type' => $this->normaliseNoteType($note['note_type'] ?? null),
                'note' => (string) ($note['note'] ?? ''),
                'author_name' => $this->nullableString($note['author_name'] ?? null),
                'noted_at' => $this->nullableString($note['created_at'] ?? null),
            ];
        }

        return $normalised;
    }

    private function normaliseSessionNotes(array $history): array
    {
        $normalised = [];

        foreach ($history as $entry) {
            if (($entry['type'] ?? null) !== 'handover') {
                continue;
            }

            $id = (int) ($entry['ref_id'] ?? $entry['id'] ?? 0);
            if ($id < 1) {
                continue;
            }

            $date = $this->nullableString($entry['session_date'] ?? null);
            if ($date === null) {
                $date = substr((string) ($entry['date'] ?? ''), 0, 10);
            }
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                continue;
            }

            $notes = $this->sessionNotesText($entry);

            $normalised[] = [
                'wordpress_handover_id' => $id,
                'date' => $date,
                'author_name' => $this->nullableString($entry['author'] ?? $entry['author_name'] ?? null),
                'session_type' => $this->normaliseSessionType($entry['support_type'] ?? null),
                'end_mood' => $this->normaliseMood($entry['mood_overall'] ?? null),
                'activities' => $this->nullableString($entry['activities'] ?? null),
                'notes' => $notes,
                'concern' => (bool) ($entry['concerns'] ?? false),
                'concern_detail' => $this->nullableString($entry['concern_detail'] ?? null),
            ];
        }

        return $normalised;
    }

    private function sessionNotesText(array $entry): ?string
    {
        $parts = [];

        foreach (['diary_achievements', 'personal_outcomes', 'daily_note'] as $field) {
            $value = $this->nullableString($entry[$field] ?? null);
            if ($value !== null && ! in_array($value, $parts, true)) {
                $parts[] = $value;
            }
        }

        $fluid = $this->nullableString($entry['fluid_intake'] ?? null);
        if ($fluid !== null) {
            $parts[] = 'Fluid intake: '.$fluid;
        }

        $food = $this->nullableString($entry['food_eaten'] ?? null);
        if ($food !== null) {
            $parts[] = 'Food: '.$food;
        }

        if ($parts === []) {
            $body = $this->nullableString($entry['body'] ?? null);
            if ($body !== null) {
                $parts[] = $body;
            }
        }

        return $parts === [] ? null : implode("\n", $parts);
    }

    private function syncMemberNote(Member $member, array $remoteNote): string
    {
        $sourceKey = (string) $remoteNote['source_key'];
        $legacyId = $remoteNote['legacy_wordpress_note_id'];

        $note = MemberNote::where('wordpress_source_key', $sourceKey)->first();

        // Stage 1 stored ordinary staff notes by their numeric WordPress ID.
        // Reuse those rows so the corrected sync does not create duplicates.
        if ($note === null && $legacyId !== null) {
            $note = MemberNote::where('wordpress_note_id', $legacyId)->first();
        }

        $notePayload = [
            'member_id' => $member->id,
            'wordpress_source_key' => $sourceKey,
            'wordpress_note_id' => $legacyId,
            'note_type' => (string) $remoteNote['note_type'],
            'note' => (string) $remoteNote['note'],
            'author_name' => $remoteNote['author_name'],
            'noted_at' => $remoteNote['noted_at'],
            'source' => 'wordpress',
        ];

        if ($note === null) {
            MemberNote::create($notePayload);

            return 'created';
        }

        // Compare decrypted text before updating. Calling fill() first would
        // re-encrypt it with a fresh IV and make unchanged text appear dirty.
        $changed = $note->member_id !== $notePayload['member_id']
            || $note->wordpress_source_key !== $notePayload['wordpress_source_key']
            || $note->wordpress_note_id !== $notePayload['wordpress_note_id']
            || $note->note_type !== $notePayload['note_type']
            || $note->note !== $notePayload['note']
            || $note->author_name !== $notePayload['author_name']
            || $note->noted_at?->format('Y-m-d H:i:s') !== $notePayload['noted_at']
            || $note->source !== $notePayload['source'];

        if (! $changed) {
            return 'unchanged';
        }

        $note->update($notePayload);

        return 'updated';
    }

    private function syncEndOfDayRecord(
        Member $member,
        array $remote,
        array $usersByName,
        int $fallbackUserId,
    ): string {
        $wordpressId = (int) $remote['wordpress_handover_id'];
        $record = EndOfDayRecord::where('wordpress_handover_id', $wordpressId)->first();

        if ($record === null) {
            $record = EndOfDayRecord::where('member_id', $member->id)
                ->whereDate('date', $remote['date'])
                ->first();
        }

        $authorName = $remote['author_name'];
        $authorKey = $this->personKey((string) $authorName);
        $userId = $usersByName[$authorKey] ?? $fallbackUserId;

        $payload = [
            'member_id' => $member->id,
            'wordpress_handover_id' => $wordpressId,
            'source' => 'wordpress',
            'source_author_name' => $authorName,
            'date' => $remote['date'],
            'user_id' => $userId,
            'end_mood' => $remote['end_mood'],
            'session_type' => $remote['session_type'],
            'activities' => $remote['activities'],
            'notes' => $remote['notes'],
            'concern' => $remote['concern'],
            'concern_detail' => $remote['concern_detail'],
        ];

        if ($record === null) {
            EndOfDayRecord::create($payload);

            return 'created';
        }

        // Never overwrite a record genuinely created in the standalone Hub.
        // Link it to the old WordPress row and fill only gaps instead.
        if (($record->source ?? 'hub') !== 'wordpress') {
            $safePayload = [
                'wordpress_handover_id' => $wordpressId,
                'source_author_name' => $record->source_author_name ?: $authorName,
            ];

            foreach (['end_mood', 'session_type', 'activities', 'notes', 'concern_detail'] as $field) {
                if (($record->{$field} === null || $record->{$field} === '') && $payload[$field] !== null) {
                    $safePayload[$field] = $payload[$field];
                }
            }

            if (! $record->concern && $payload['concern']) {
                $safePayload['concern'] = true;
            }

            $record->fill($safePayload);
        } else {
            $record->fill($payload);
        }

        if (! $record->isDirty()) {
            return 'unchanged';
        }

        $record->save();

        return 'updated';
    }

    private function memberPayload(array $remote, bool $isNew): array
    {
        $medical = $this->splitMedicalProfile($remote['medical_notes'] ?? null);

        $payload = [
            'wordpress_id' => (int) $remote['id'],
            'dob' => $this->nullableString($remote['date_of_birth'] ?? null),
            'support_needs' => $this->mergeText(
                $remote['support_needs'] ?? null,
                $medical['support_needs'],
            ),
            'medical_notes' => $medical['medical_notes'],
            'interests' => $this->mergeText(
                $remote['interests'] ?? null,
                $medical['interests'],
            ),
        ];

        // Only replace native medication/diagnosis fields when WordPress
        // actually supplies a matching section. This preserves any standalone
        // data for profiles whose old medical note did not contain a section.
        if ($isNew || $medical['medication'] !== null) {
            $payload['medication'] = $medical['medication'];
        }
        if ($isNew || $medical['diagnoses'] !== null) {
            $payload['diagnoses'] = $medical['diagnoses'];
        }

        if (! $isNew) {
            return $payload;
        }

        return [
            ...$payload,
            'first_name' => trim((string) ($remote['first_name'] ?? '')),
            'last_name' => trim((string) ($remote['last_name'] ?? '')),
            'preferred_name' => $this->nullableString($remote['preferred_name'] ?? null),
            'status' => ($remote['status'] ?? 'active') === 'active' ? 'active' : 'inactive',
            'phone' => $this->nullableString($remote['phone'] ?? null),
            'email' => $this->nullableString($remote['email'] ?? null),
            'address_line1' => $this->nullableString($remote['address'] ?? null),
            'gp_name' => $this->nullableString($remote['gp_name'] ?? null),
            'gp_practice' => $this->nullableString($remote['gp_address'] ?? null),
            'gp_phone' => $this->nullableString($remote['gp_phone'] ?? null),
        ];
    }

    /**
     * WordPress used one free-text medical box containing headings such as
     * MEDICATION, CONDITIONS and ALLERGIES. Split recognised headings into the
     * standalone Hub's real fields and leave all other medical text in notes.
     */
    private function splitMedicalProfile(mixed $value): array
    {
        $text = $this->nullableString($value);
        $result = [
            'medication' => null,
            'diagnoses' => null,
            'support_needs' => null,
            'interests' => null,
            'medical_notes' => null,
        ];

        if ($text === null) {
            return $result;
        }

        $buckets = [
            'medication' => [],
            'diagnoses' => [],
            'support_needs' => [],
            'interests' => [],
            'medical_notes' => [],
        ];
        $current = 'medical_notes';

        foreach (preg_split('/\R/u', str_replace(["\r\n", "\r"], "\n", $text)) ?: [] as $line) {
            if (preg_match('/^\s*([A-Z][A-Z0-9 &\/-]{2,})\s*:\s*(.*)$/iu', $line, $matches)) {
                $heading = mb_strtoupper(trim($matches[1]));
                $mapped = match ($heading) {
                    'MEDICATION', 'MEDICATIONS' => 'medication',
                    'DIAGNOSIS', 'DIAGNOSES', 'CONDITION', 'CONDITIONS' => 'diagnoses',
                    'SUPPORT NEED', 'SUPPORT NEEDS' => 'support_needs',
                    'INTEREST', 'INTERESTS' => 'interests',
                    default => 'medical_notes',
                };

                $current = $mapped;
                $remainder = trim($matches[2]);

                if ($mapped === 'medical_notes' && $remainder !== '') {
                    $buckets[$current][] = ucfirst(mb_strtolower($heading)).': '.$remainder;
                } elseif ($mapped === 'medical_notes') {
                    $buckets[$current][] = ucfirst(mb_strtolower($heading)).':';
                } elseif ($remainder !== '') {
                    $buckets[$current][] = $remainder;
                }

                continue;
            }

            $buckets[$current][] = rtrim($line);
        }

        foreach ($buckets as $key => $lines) {
            $clean = trim(implode("\n", $lines));
            $result[$key] = $clean === '' ? null : $clean;
        }

        return $result;
    }

    private function mergeText(mixed ...$values): ?string
    {
        $parts = [];

        foreach ($values as $value) {
            $text = $this->nullableString($value);
            if ($text !== null && ! in_array($text, $parts, true)) {
                $parts[] = $text;
            }
        }

        return $parts === [] ? null : implode("\n\n", $parts);
    }

    private function userLookup(): array
    {
        $users = User::query()->get(['id', 'name', 'role']);
        $fallback = $users->first(fn (User $user) => in_array($user->role, ['manager', 'administrator'], true))
            ?? $users->first();

        if ($fallback === null) {
            throw new RuntimeException('The standalone Hub needs at least one user before historical session records can be synced.');
        }

        $byName = [];
        foreach ($users as $user) {
            $key = $this->personKey($user->name);
            if ($key !== '') {
                $byName[$key] = $user->id;
            }
        }

        return [$byName, $fallback->id];
    }

    private function normaliseSessionType(mixed $type): ?string
    {
        return match (strtolower(trim((string) $type))) {
            'individual' => 'Individual support',
            'group' => 'Group session',
            'community' => 'Community session',
            default => null,
        };
    }

    private function normaliseMood(mixed $mood): ?string
    {
        return match (strtolower(trim((string) $mood))) {
            'excellent', 'good' => 'happy',
            'okay' => 'neutral',
            'low' => 'sad',
            'distressed' => 'anxious',
            default => null,
        };
    }

    private function request(): PendingRequest
    {
        [$username, $password] = $this->credentials();

        return Http::acceptJson()
            ->withBasicAuth($username, $password)
            ->withHeaders([
                'X-Areterra-Sync' => 'Laravel-Cloud',
            ])
            ->connectTimeout(10)
            ->timeout(60);
    }

    /**
     * Send a GET request without creating a rapid retry burst. Shared WordPress
     * hosts commonly return 429 when several requests arrive together, so any
     * retry waits before trying again and honours a short Retry-After header.
     */
    private function get(string $url, array $query = []): Response
    {
        $attempt = 0;
        $maxAttempts = 3;

        while (true) {
            $attempt++;

            try {
                $response = $this->request()->get($url, $query);
            } catch (ConnectionException $exception) {
                if ($attempt >= $maxAttempts) {
                    throw $exception;
                }

                sleep($attempt * 2);
                continue;
            }

            $shouldRetry = $response->status() === 429 || $response->serverError();
            if (! $shouldRetry || $attempt >= $maxAttempts) {
                return $response;
            }

            $retryAfter = (int) $response->header('Retry-After');
            $delay = $retryAfter > 0
                ? min(10, max(2, $retryAfter))
                : $attempt * 3;

            sleep($delay);
        }
    }

    private function endpoint(string $path): string
    {
        $url = trim((string) Setting::get('wordpress_url'));
        if ($url === '') {
            throw new RuntimeException('WordPress URL has not been configured in Hub Settings.');
        }

        $url = rtrim($url, '/');
        if (! str_contains($url, '/wp-json/')) {
            $url .= '/wp-json/areterra-hub/v1';
        }

        return $url.'/'.ltrim($path, '/');
    }

    private function credentials(): array
    {
        $username = trim((string) Setting::get('wordpress_username'));
        $encryptedPassword = Setting::get('wordpress_application_password');

        if ($username === '' || $encryptedPassword === null || $encryptedPassword === '') {
            throw new RuntimeException('WordPress username and Application Password must be saved in Hub Settings first.');
        }

        try {
            $password = decrypt($encryptedPassword);
        } catch (Throwable) {
            throw new RuntimeException('The saved WordPress Application Password could not be decrypted. Save it again in Hub Settings.');
        }

        return [$username, (string) $password];
    }

    private function unwrap(Response $response): array
    {
        if (! $response->successful()) {
            if ($response->status() === 429) {
                throw new RuntimeException(
                    'The WordPress host is temporarily rate-limiting requests (HTTP 429). Wait a few minutes for the block to clear and press Sync again.'
                );
            }

            $message = data_get($response->json(), 'message')
                ?? "WordPress returned HTTP {$response->status()}.";
            throw new RuntimeException((string) $message);
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new RuntimeException('WordPress returned an invalid response.');
        }

        if (($payload['success'] ?? false) !== true || ! array_key_exists('data', $payload)) {
            $message = $payload['message'] ?? 'WordPress did not return the expected Areterra Hub response.';
            throw new RuntimeException((string) $message);
        }

        return $payload;
    }

    private function nameKey(string $firstName, string $lastName): string
    {
        return mb_strtolower(trim($firstName).'|'.trim($lastName));
    }

    private function personKey(string $name): string
    {
        return preg_replace('/[^a-z0-9]+/', '', mb_strtolower($name)) ?? '';
    }

    private function normaliseNoteType(mixed $type): string
    {
        $type = strtolower(trim((string) $type));

        return in_array($type, ['general', 'concern', 'progress', 'handover'], true)
            ? $type
            : 'general';
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
