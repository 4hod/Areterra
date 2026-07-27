<?php

namespace App\Services;

use App\Models\Member;
use App\Models\MemberNote;
use App\Models\Setting;
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
        $response = $this->request()->get($this->endpoint('members'), [
            'page' => 1,
            'per_page' => 1,
            'status' => '',
        ]);

        $payload = $this->unwrap($response);

        return [
            'connected' => true,
            'members_available' => (int) data_get($payload, 'meta.total', count($payload['data'])),
        ];
    }

    public function syncMembers(): array
    {
        $remoteMembers = $this->downloadMembers();

        $result = DB::transaction(function () use ($remoteMembers) {
            $created = 0;
            $updated = 0;
            $notesCreated = 0;
            $notesUpdated = 0;

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

                foreach ($remote['session_notes'] as $remoteNote) {
                    $change = $this->syncMemberNote($member, $remoteNote);
                    $notesCreated += $change === 'created' ? 1 : 0;
                    $notesUpdated += $change === 'updated' ? 1 : 0;
                }
            }

            return [
                'members_received' => count($remoteMembers),
                'members_created' => $created,
                'members_updated' => $updated,
                'notes_created' => $notesCreated,
                'notes_updated' => $notesUpdated,
            ];
        });

        Setting::set('wordpress_last_sync_at', now()->toIso8601String());
        Setting::set('wordpress_last_sync_summary', json_encode($result, JSON_THROW_ON_ERROR));

        return $result;
    }

    private function downloadMembers(): array
    {
        $members = [];
        $page = 1;

        do {
            $payload = $this->unwrap($this->request()->get($this->endpoint('members'), [
                'page' => $page,
                'per_page' => 100,
                'status' => '',
            ]));

            foreach ($payload['data'] as $summary) {
                $id = (int) ($summary['id'] ?? 0);
                if ($id < 1) {
                    continue;
                }

                $detail = $this->unwrap($this->request()->get($this->endpoint("members/{$id}")))['data'];
                $notes = $this->unwrap($this->request()->get($this->endpoint("members/{$id}/notes")))['data'];
                $history = $this->unwrap($this->request()->get(
                    $this->endpoint("members/{$id}/history"),
                    ['per_page' => 100],
                ))['data'];

                $members[] = [
                    'member' => $detail,
                    'notes' => $this->normaliseMemberNotes(is_array($notes) ? $notes : []),
                    'session_notes' => $this->normaliseSessionNotes(is_array($history) ? $history : []),
                ];
            }

            $totalPages = max(1, (int) data_get($payload, 'meta.total_pages', 1));
            $page++;
        } while ($page <= $totalPages);

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

            $id = (int) ($entry['ref_id'] ?? 0);
            if ($id < 1) {
                continue;
            }

            $body = trim((string) ($entry['body'] ?? ''));
            if ($body === '') {
                $body = trim((string) ($entry['title'] ?? 'End of Day Record'));
            }

            $normalised[] = [
                'source_key' => "session-handover:{$id}",
                'legacy_wordpress_note_id' => null,
                'note_type' => 'end_of_day',
                'note' => $body,
                'author_name' => $this->nullableString($entry['author'] ?? null),
                'noted_at' => $this->nullableString($entry['date'] ?? null),
            ];
        }

        return $normalised;
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

        // Compare the decrypted note text before updating. Calling fill() first would
        // re-encrypt the text with a fresh IV and make an unchanged note look dirty.
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

    private function memberPayload(array $remote, bool $isNew): array
    {
        $payload = [
            'wordpress_id' => (int) $remote['id'],
            'dob' => $this->nullableString($remote['date_of_birth'] ?? null),
            'support_needs' => $this->nullableString($remote['support_needs'] ?? null),
            'medical_notes' => $this->nullableString($remote['medical_notes'] ?? null),
            'interests' => $this->nullableString($remote['interests'] ?? null),
        ];

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

    private function request(): PendingRequest
    {
        [$username, $password] = $this->credentials();

        return Http::acceptJson()
            ->withBasicAuth($username, $password)
            ->timeout(30)
            ->retry(2, 300);
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
