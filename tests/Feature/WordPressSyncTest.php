<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\MemberNote;
use App\Models\Setting;
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
    }

    public function test_it_updates_existing_member_dob_and_imports_notes_without_duplicates(): void
    {
        $member = Member::create([
            'first_name' => 'Amy',
            'last_name' => 'Buckle',
        ]);
        $member->settings()->create(['attendance_days' => [2, 4, 5]]);

        Http::fake(function (Request $request) {
            $this->assertStringStartsWith('Basic ', $request->header('Authorization')[0] ?? '');
            $url = $request->url();

            if (str_contains($url, '/members?')) {
                return Http::response([
                    'success' => true,
                    'data' => [[
                        'id' => 42,
                        'first_name' => 'Amy',
                        'last_name' => 'Buckle',
                    ]],
                    'meta' => [
                        'total' => 1,
                        'page' => 1,
                        'per_page' => 100,
                        'total_pages' => 1,
                    ],
                ]);
            }

            if (str_ends_with($url, '/members/42/notes')) {
                return Http::response([
                    'success' => true,
                    'data' => [[
                        'id' => 9001,
                        'member_id' => 42,
                        'user_id' => 7,
                        'author_name' => 'Ethan',
                        'note' => 'Enjoyed animal care and was settled throughout the day.',
                        'note_type' => 'progress',
                        'created_at' => '2026-07-20 14:30:00',
                    ]],
                ]);
            }

            if (str_contains($url, '/members/42/history')) {
                return Http::response([
                    'success' => true,
                    'data' => [[
                        'type' => 'handover',
                        'subtype' => 'handover',
                        'date' => '2026-06-18 14:00:00',
                        'author' => 'robbodley',
                        'title' => 'End of Day Record',
                        'body' => 'Did coop shop and collected bunnies supplies then had a crafty afternoon.',
                        'ref_id' => 77,
                    ]],
                ]);
            }

            if (str_ends_with($url, '/members/42')) {
                return Http::response([
                    'success' => true,
                    'data' => [
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
                        'medical_notes' => 'No known allergies.',
                        'support_needs' => 'Benefits from clear verbal prompts.',
                        'interests' => 'Animals and gardening.',
                    ],
                ]);
            }

            return Http::response(['message' => 'Unexpected URL: '.$url], 500);
        });

        $service = app(WordPressSyncService::class);
        $first = $service->syncMembers();
        $second = $service->syncMembers();

        $member->refresh();
        $this->assertSame(1, Member::count());
        $this->assertSame(42, $member->wordpress_id);
        $this->assertSame('1994-03-12', $member->dob->toDateString());
        $this->assertSame('No known allergies.', $member->medical_notes);
        $this->assertSame('Benefits from clear verbal prompts.', $member->support_needs);
        $this->assertSame('Animals and gardening.', $member->interests);

        $this->assertSame(2, MemberNote::count());
        $note = MemberNote::where('note_type', 'progress')->firstOrFail();
        $this->assertSame(9001, $note->wordpress_note_id);
        $this->assertSame('progress', $note->note_type);
        $this->assertSame('Ethan', $note->author_name);
        $this->assertStringContainsString('Enjoyed animal care', $note->note);

        $sessionNote = MemberNote::where('note_type', 'end_of_day')->firstOrFail();
        $this->assertSame('session-handover:77', $sessionNote->wordpress_source_key);
        $this->assertSame('robbodley', $sessionNote->author_name);
        $this->assertStringContainsString('coop shop', $sessionNote->note);

        $this->assertSame(2, $first['notes_created']);
        $this->assertSame(0, $second['notes_created']);
    }

    public function test_connection_check_reports_available_members(): void
    {
        Http::fake([
            'https://wordpress.test/wp-json/areterra-hub/v1/members*' => Http::response([
                'success' => true,
                'data' => [],
                'meta' => ['total' => 11, 'total_pages' => 1],
            ]),
        ]);

        $result = app(WordPressSyncService::class)->testConnection();

        $this->assertTrue($result['connected']);
        $this->assertSame(11, $result['members_available']);
    }
}
