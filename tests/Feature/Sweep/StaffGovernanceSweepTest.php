<?php

namespace Tests\Feature\Sweep;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class StaffGovernanceSweepTest extends SweepTestCase
{
    public function test_leave_request_and_review(): void
    {
        $this->get('/leave')->assertOk();

        $this->assertWriteOk($this->post('/leave', [
            'type' => 'annual',
            'start_date' => now()->addWeek()->toDateString(),
            'end_date' => now()->addWeek()->addDays(4)->toDateString(),
            'reason' => 'Family holiday',
        ]), 'leave.store');
        $l = DB::table('leave_requests')->first();
        $this->assertNotNull($l, 'leave request not created');

        $this->assertWriteOk($this->put("/leave/{$l->id}/review", [
            'status' => 'approved', 'review_notes' => 'Cover arranged.',
        ]), 'leave.review');
        $this->assertSame('approved', DB::table('leave_requests')->find($l->id)->status);
    }

    public function test_timeclock_in_out_and_correction(): void
    {
        $this->get('/timeclock')->assertOk();

        $this->assertWriteOk($this->post('/timeclock/in'), 'timeclock.in');
        $e = DB::table('timeclock_entries')->where('user_id', $this->admin->id)->first();
        $this->assertNotNull($e, 'clock-in not recorded');
        $this->assertNull($e->clock_out, 'clock_out set on clock-in');

        $this->assertWriteOk($this->post('/timeclock/out', ['break_minutes' => 30]), 'timeclock.out');
        $this->assertNotNull(DB::table('timeclock_entries')->find($e->id)->clock_out, 'clock-out not recorded');

        $this->assertWriteOk($this->put("/timeclock/{$e->id}", [
            'clock_in' => now()->subHours(8)->toDateTimeString(),
            'clock_out' => now()->toDateTimeString(),
            'break_minutes' => 45, 'notes' => 'Forgot to clock out.',
        ]), 'timeclock.update');
        $this->assertEquals(45, DB::table('timeclock_entries')->find($e->id)->break_minutes);
    }

    public function test_supervisions_recorded_against_a_subject(): void
    {
        $this->get('/supervisions')->assertOk();

        $this->assertWriteOk($this->post('/supervisions', [
            'subject_key' => "user:{$this->admin->id}",
            'type' => 'supervision',
            'date' => now()->toDateString(),
            'duration_minutes' => 45,
            'discussion' => 'Caseload and wellbeing.',
            'actions_agreed' => 'Book training.',
        ]), 'supervisions.store');
        $this->assertNotNull(DB::table('supervisions')->first(), 'supervision not created');
    }

    public function test_policies_create_update_and_approve(): void
    {
        $this->get('/policies')->assertOk();

        $this->assertWriteOk($this->post('/policies', [
            'title' => 'Lone Working Policy', 'category' => 'Safeguarding',
            'body' => 'Staff must not lone work without a check-in call.',
            'version' => '1.0', 'status' => 'draft',
            'review_date' => now()->addYear()->toDateString(),
        ]), 'policies.store');
        $p = DB::table('policies')->where('title', 'Lone Working Policy')->first();
        $this->assertNotNull($p, 'policy not created');

        $this->get("/policies/{$p->id}")->assertOk();

        $this->assertWriteOk($this->put("/policies/{$p->id}", [
            'title' => 'Lone Working Policy', 'body' => 'Updated wording.',
            'version' => '1.1', 'status' => 'active',
        ]), 'policies.update');
        $this->assertSame('1.1', DB::table('policies')->find($p->id)->version);

        $this->assertWriteOk($this->post("/policies/{$p->id}/approve"), 'policies.approve');
    }

    public function test_risk_assessments_create_update_and_sign_off(): void
    {
        $this->get('/risk-assessments')->assertOk();

        $this->assertWriteOk($this->post('/risk-assessments', [
            'title' => 'Handling the macaws', 'description' => 'Bite risk during handling.',
            'likelihood' => 3, 'severity' => 4,
            'control_measures' => 'Gloves, two-person handling.',
            'review_date' => now()->addMonths(6)->toDateString(),
        ]), 'risks.store');
        $r = DB::table('risk_assessments')->where('title', 'Handling the macaws')->first();
        $this->assertNotNull($r, 'risk assessment not created');

        $this->assertWriteOk($this->put("/risk-assessments/{$r->id}", [
            'title' => 'Handling the macaws', 'likelihood' => 2, 'severity' => 4,
            'control_measures' => 'Gloves, two-person handling, refresher training.',
        ]), 'risks.update');
        $this->assertEquals(2, DB::table('risk_assessments')->find($r->id)->likelihood);

        $this->assertWriteOk($this->post("/risk-assessments/{$r->id}/sign-off"), 'risks.sign-off');
    }

    public function test_documents_upload_download_and_read_receipt(): void
    {
        Storage::fake('local');
        $this->get('/documents')->assertOk();

        $this->assertWriteOk($this->post('/documents', [
            'title' => 'Fire Evacuation Plan', 'category' => 'Premises',
            'file' => UploadedFile::fake()->create('evac.pdf', 64, 'application/pdf'),
            'requires_read' => true,
        ]), 'documents.store');
        $d = DB::table('documents')->where('title', 'Fire Evacuation Plan')->first();
        $this->assertNotNull($d, 'document not created');

        $this->get("/documents/{$d->id}/download")->assertOk();

        $this->assertWriteOk($this->post("/documents/{$d->id}/read"), 'documents.read');
        $this->assertNotNull(
            DB::table('document_reads')->where('document_id', $d->id)->first(),
            'read receipt not recorded'
        );
    }

    public function test_announcements_and_recognition(): void
    {
        $this->get('/announcements')->assertOk();

        $this->assertWriteOk($this->post('/announcements', [
            'title' => 'Site closed Monday', 'body' => 'Snow forecast — stay home.',
        ]), 'announcements.store');
        $a = DB::table('announcements')->first();
        $this->assertNotNull($a, 'announcement not created');

        $this->assertWriteOk($this->post("/announcements/{$a->id}/read"), 'announcements.read');
        $this->assertNotNull(
            DB::table('announcement_reads')->where('announcement_id', $a->id)->first(),
            'announcement read receipt not recorded'
        );

        $this->get('/recognition')->assertOk();
        $this->assertWriteOk($this->post('/recognition', [
            'recipient' => 'Jo Carer', 'message' => 'Brilliant with the new starters.',
        ]), 'recognition.store');
        $r = DB::table('recognitions')->first();
        $this->assertNotNull($r, 'recognition not created');

        $this->assertWriteOk($this->post("/recognition/{$r->id}/like"), 'recognition.like');
        $this->assertNotNull(DB::table('recognition_likes')->first(), 'like not recorded');

        // Toggling off should remove it, not add a second row.
        $this->assertWriteOk($this->post("/recognition/{$r->id}/like"), 'recognition.like (toggle off)');
        $this->assertSame(0, DB::table('recognition_likes')->count(), 'like did not toggle off');
    }

    public function test_member_reviews_and_safeguarding(): void
    {
        $m = $this->member();
        $this->get('/reviews')->assertOk();

        $this->assertWriteOk($this->post('/reviews', [
            'member_id' => $m->id, 'review_date' => now()->toDateString(),
            'outcomes' => 'Settled well.', 'actions' => 'Continue current plan.',
            'next_review_date' => now()->addMonths(6)->toDateString(),
        ]), 'reviews.store');
        $this->assertNotNull(DB::table('member_reviews')->first(), 'review not created');

        // Safeguarding sits behind a password-confirmation wall.
        $this->session(['auth.password_confirmed_at' => time()]);
        $this->get('/safeguarding')->assertOk();

        $this->assertWriteOk($this->post('/safeguarding', [
            'member_id' => $m->id, 'date' => now()->toDateString(),
            'details' => 'Disclosure made during session.',
            'actions_taken' => 'Escalated to the safeguarding lead.',
        ]), 'safeguarding.store');
        $c = DB::table('safeguarding_concerns')->first();
        $this->assertNotNull($c, 'safeguarding concern not created');

        $this->assertWriteOk($this->put("/safeguarding/{$c->id}", [
            'status' => 'closed', 'actions_taken' => 'Case closed after review.',
        ]), 'safeguarding.update');
        $this->assertSame('closed', DB::table('safeguarding_concerns')->find($c->id)->status);
    }

    public function test_sar_requests_and_referrals(): void
    {
        $m = $this->member();
        $this->get('/sar-requests')->assertOk();

        $this->assertWriteOk($this->post('/sar-requests', [
            'requester_name' => 'A Parent', 'requester_relationship' => 'Mother',
            'member_id' => $m->id, 'received_date' => now()->toDateString(),
        ]), 'sar-requests.store');
        $s = DB::table('sar_requests')->first();
        $this->assertNotNull($s, 'SAR request not created');

        $this->assertWriteOk($this->put("/sar-requests/{$s->id}", [
            'status' => 'fulfilled', 'notes' => 'Pack sent by recorded delivery.',
        ]), 'sar-requests.update');
        $this->assertSame('fulfilled', DB::table('sar_requests')->find($s->id)->status);

        // Public referral form — no auth.
        $this->post('/refer', [
            'referrer_name' => 'GP Surgery', 'referrer_email' => 'gp@example.test',
            'person_name' => 'New Person', 'details' => 'Would benefit from animal contact.',
        ]);
        $ref = DB::table('referrals')->first();
        $this->assertNotNull($ref, 'public referral not created');

        $this->get('/referrals')->assertOk();
        $this->assertWriteOk($this->put("/referrals/{$ref->id}/review", [
            'status' => 'accepted',
        ]), 'referrals.review');
        $this->assertSame('accepted', DB::table('referrals')->find($ref->id)->status);
    }

    public function test_forms_builder_submission_and_toggle(): void
    {
        $this->get('/forms')->assertOk();
        $this->get('/forms/new')->assertOk();

        $this->assertWriteOk($this->post('/forms', [
            'title' => 'Volunteer Sign-up',
            'description' => 'Tell us about yourself.',
            'fields' => [
                ['label' => 'Full name', 'type' => 'text', 'required' => true],
                ['label' => 'Availability', 'type' => 'select', 'options' => ['Weekdays', 'Weekends']],
            ],
        ]), 'forms.store');
        $f = DB::table('form_definitions')->where('title', 'Volunteer Sign-up')->first();
        $this->assertNotNull($f, 'form not created');

        $this->get("/forms/{$f->slug}")->assertOk();

        // Answers are posted as field_{id}, one key per defined field.
        $fields = DB::table('form_fields')->where('form_id', $f->id)->get();
        $this->assertCount(2, $fields, 'form fields not persisted');
        $answers = [];
        foreach ($fields as $i => $field) {
            $answers["field_{$field->id}"] = $i === 0 ? 'Sam Volunteer' : 'Weekends';
        }

        $this->assertWriteOk($this->post("/forms/{$f->slug}/submissions", $answers), 'forms.submit');
        $sub = DB::table('form_submissions')->where('form_id', $f->id)->first();
        $this->assertNotNull($sub, 'submission not recorded');
        $this->assertSame(
            2,
            DB::table('form_submission_data')->where('submission_id', $sub->id)->count(),
            'submitted answers not stored'
        );

        $this->get("/forms/{$f->slug}/submissions")->assertOk();

        $this->assertWriteOk($this->put("/forms/{$f->id}/toggle"), 'forms.toggle');
    }

    public function test_ops_cluster_projects_funding_and_insurance(): void
    {
        foreach ([
            ['projects', ['title' => 'New paddock', 'status' => 'planning'], 'projects', 'title'],
            ['funding', ['title' => 'Wildlife grant', 'status' => 'identified'], 'funding_opportunities', 'title'],
            ['insurance', ['policy_type' => 'Public Liability', 'provider' => 'Insurer Ltd'], 'insurance_policies', 'policy_type'],
        ] as [$route, $payload, $table, $key]) {
            $this->get("/$route")->assertOk();

            $this->assertWriteOk($this->post("/$route", $payload), "$route.store");
            $row = DB::table($table)->where($key, $payload[$key])->first();
            $this->assertNotNull($row, "$route record not created");

            $this->assertWriteOk($this->put("/$route/{$row->id}", $payload), "$route.update");
            $this->assertWriteOk($this->delete("/$route/{$row->id}"), "$route.destroy");
            $this->assertGone($table, $row->id, "$route.destroy");
        }
    }

    public function test_settings_notifications_and_account(): void
    {
        $this->get('/settings')->assertOk();
        $this->assertWriteOk($this->put('/settings', [
            'org_name' => 'Areterra', 'reply_to' => 'hello@areterra.test',
            'banner_text' => 'Welcome to the Hub.',
        ]), 'settings.update');

        $this->get('/notifications')->assertOk();
        $this->get('/notifications/recent')->assertOk();
        $this->assertWriteOk($this->put('/notifications', [
            'push_enabled' => false, 'email_enabled' => true,
            'categories' => ['announcements' => true, 'concerns' => true],
        ]), 'notifications.update');

        $this->get('/account')->assertOk();
        $this->assertWriteOk($this->put('/account/profile', [
            'name' => 'Sweep Admin', 'job_title' => 'Operations Director',
            'phone' => '01905 123456', 'bio' => 'Runs the place.',
        ]), 'account.profile');
        $this->assertSame('Operations Director', $this->admin->fresh()->job_title);
    }

    public function test_read_only_pages_all_load(): void
    {
        foreach ([
            '/', '/today', '/directory', '/calendar', '/search?q=test',
            '/audit', '/audit-log', '/reports', '/import', '/more', '/email',
            '/reports/members.csv', '/reports/animals.csv',
            '/reports/activities.csv', '/reports/hours.csv',
        ] as $url) {
            $r = $this->get($url);
            $this->assertTrue(
                in_array($r->getStatusCode(), [200, 302], true),
                "$url returned HTTP ".$r->getStatusCode()
            );
        }
    }
}
