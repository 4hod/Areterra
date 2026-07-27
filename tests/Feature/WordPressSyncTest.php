<?php

namespace Tests\Feature;

use App\Models\EndOfDayRecord;
use App\Models\Member;
use App\Models\MemberNote;
use App\Models\Setting;
use App\Models\User;
use App\Services\WordPressSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WordPressSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.key' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
            'app.cipher' => 'AES-256-CBC',
        ]);

        Setting::set('wordpress_url', 'https://wordpress.test');
        Setting::set('wordpress_username', 'sync-user');
        Setting::set('wordpress_application_password', encrypt('abcd efgh ijkl mnop'));

        User::factory()->create([
            'name' => 'Rob Bodley',
            'role' => 'manager',
        ]);
    }

    public function test_bulk_sync_uses_native_profile_and_end_of_day_fields_without_duplicates(): void
    {
        $member = Member::create([
            'first_name' => 'Amy',
            'last_name' => 'Buckle',
        ]);
        $member->settings()->create(['attendance_days' => [2, 4, 5]]);

        Http::fake(function (Request $request) {
            $this->assertStringStartsWith('Basic ', $request->header('Authorization')[0] ?? '');
            $this->assertStringContainsString('/sync/members', $request->url());

            return Http::response([
                'success' => true,
                'data' => [[
                    'member' => [
                        'id' => 42,
                        'first_name' => 'Amy',
                        'last_name' => 'Buckle',
                        'preferred_name' => null,
                        'status' => 'active',
                        'date_of_birth' => '1994-03-12',
                        'address' => null,
                        'email' => null,
                        'phone' => null,
                        'gp_name' => null,
                        'gp_phone' => null,
                        'gp_address' => null,
                        'medical_notes' => "MEDICATION:\n- Levothyroxine 75mcg daily\n\nCONDITIONS: Hypothyroidism\nALLERGIES: No known allergies.",
                        'support_needs' => 'Benefits from clear verbal prompts.',
                        'interests' => 'Animals and gardening.',
                    ],
                    'notes' => [[
                        'id' => 9001,
                        'member_id' => 42,
                        'user_id' => 7,
                        'author_name' => 'Ethan',
                        'note' => 'Enjoyed animal care and was settled throughout the day.',
                        'note_type' => 'progress',
                        'created_at' => '2026-07-20 14:30:00',
                    ]],
                    'session_notes' => [[
                        'type' => 'handover',
                        'subtype' => 'handover',
                        'date' => '2026-06-18 14:00:00',
                        'author' => 'robbodley',
                        'title' => 'End of Day Record',
                        'body' => 'Fallback timeline body.',
                        'ref_id' => 77,
                        'session_date' => '2026-06-18',
                        'support_type' => 'group',
                        'mood_overall' => 'good',
                        'activities' => 'Craft and animal care.',
                        'diary_achievements' => 'Collected supplies.',
                        'personal_outcomes' => '',
                        'daily_note' => 'Had a positive afternoon.',
                        'fluid_intake' => 'Drank well',
                        'food_eaten' => 'Lunch eaten',
                        'concerns' => false,
                        'concern_detail' => '',
                    ]],
                ]],
                'meta' => [
                    'total' => 1,
                    'sync_version' => 3,
                    'request_count' => 1,
                ],
            ]);
        });

        $service = app(WordPressSyncService::class);
        $first = $service->syncMembers();
        $second = $service->syncMembers();

        $member->refresh();
        $this->assertSame(1, Member::count());
        $this->assertSame(42, $member->wordpress_id);
        $this->assertSame('1994-03-12', $member->dob->toDateString());
        $this->assertSame('- Levothyroxine 75mcg daily', $member->medication);
        $this->assertSame('Hypothyroidism', $member->diagnoses);
        $this->assertSame('Allergies: No known allergies.', $member->medical_notes);
        $this->assertSame('Benefits from clear verbal prompts.', $member->support_needs);
        $this->assertSame('Animals and gardening.', $member->interests);

        $this->assertSame(1, MemberNote::count());
        $note = MemberNote::firstOrFail();
        $this->assertSame(9001, $note->wordpress_note_id);
        $this->assertSame('progress', $note->note_type);
        $this->assertSame('Ethan', $note->author_name);
        $this->assertStringContainsString('Enjoyed animal care', $note->note);

        $this->assertSame(1, EndOfDayRecord::count());
        $session = EndOfDayRecord::firstOrFail();
        $this->assertSame(77, $session->wordpress_handover_id);
        $this->assertSame('wordpress', $session->source);
        $this->assertSame('robbodley', $session->source_author_name);
        $this->assertSame('2026-06-18', $session->date->toDateString());
        $this->assertSame('Group session', $session->session_type);
        $this->assertSame('happy', $session->end_mood);
        $this->assertSame('Craft and animal care.', $session->activities);
        $this->assertStringContainsString('Collected supplies.', $session->notes);
        $this->assertStringContainsString('Had a positive afternoon.', $session->notes);
        $this->assertStringContainsString('Fluid intake: Drank well', $session->notes);

        $this->assertSame(1, $first['notes_created']);
        $this->assertSame(1, $first['sessions_created']);
        $this->assertSame(0, $second['notes_created']);
        $this->assertSame(0, $second['sessions_created']);

        // One bulk request per sync, rather than several requests per member.
        Http::assertSentCount(2);
    }

    public function test_connection_check_uses_lightweight_bulk_summary(): void
    {
        Http::fake([
            'https://wordpress.test/wp-json/areterra-hub/v1/sync/members*' => Http::response([
                'success' => true,
                'data' => [],
                'meta' => [
                    'total' => 11,
                    'sync_version' => 3,
                ],
            ]),
        ]);

        $result = app(WordPressSyncService::class)->testConnection();

        $this->assertTrue($result['connected']);
        $this->assertSame(11, $result['members_available']);
        $this->assertSame(3, $result['sync_version']);

        Http::assertSent(function (Request $request) {
            return str_contains($request->url(), '/sync/members')
                && str_contains($request->url(), 'summary=1');
        });
    }
}
