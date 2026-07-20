<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\EmailTemplate;
use App\Models\Member;
use App\Models\TransportLedgerEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ChecklistGapTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::factory()->create(['role' => 'staff']);
    }

    public function test_comms_log_entries_can_be_added_and_deleted(): void
    {
        $staff = $this->staff();
        $member = Member::create(['first_name' => 'Amy', 'last_name' => 'Buckle']);

        $this->actingAs($staff)->post("/members/{$member->id}/comms", [
            'type' => 'phone',
            'direction' => 'inbound',
            'subject' => 'Social worker check-in',
            'summary' => 'Discussed transport arrangements.',
            'contact_name' => 'Jane SW',
            'date' => today()->toDateString(),
        ])->assertRedirect();

        $entry = $member->commsLog()->first();
        $this->assertSame('Discussed transport arrangements.', $entry->summary);

        $this->actingAs($staff)->delete("/members/{$member->id}/comms/{$entry->id}")->assertRedirect();
        $this->assertSame(0, $member->commsLog()->count());
    }

    public function test_email_composer_sends_with_merge_tags_and_logs_to_comms(): void
    {
        Mail::fake();
        $staff = $this->staff();
        $member = Member::create(['first_name' => 'Amy', 'last_name' => 'Buckle']);

        $this->actingAs($staff)->post('/email/send', [
            'member_id' => $member->id,
            'to_email' => 'jane@council.gov.uk',
            'to_name' => 'Jane SW',
            'subject' => 'Update on {{member_name}}',
            'body' => "Hi Jane,\n\nAn update on {{member_name}} as of {{today}}.",
            'save_template' => true,
            'template_name' => 'Member update',
        ])->assertRedirect();

        Mail::assertSent(\App\Mail\BrandedEmail::class, function ($mail) {
            return $mail->hasTo('jane@council.gov.uk')
                && $mail->emailSubject === 'Update on Amy Buckle'
                && str_contains($mail->bodyText, 'Amy Buckle');
        });

        $log = $member->commsLog()->first();
        $this->assertSame('Update on Amy Buckle', $log->subject);
        $this->assertStringContainsString('Amy Buckle', $log->summary);
        $this->assertStringNotContainsString('{{member_name}}', $log->summary);
        $this->assertSame(1, EmailTemplate::count());
    }

    public function test_email_log_only_skips_sending_but_still_logs(): void
    {
        Mail::fake();
        $staff = $this->staff();
        $member = Member::create(['first_name' => 'Amy', 'last_name' => 'Buckle']);

        $this->actingAs($staff)->post('/email/send', [
            'member_id' => $member->id,
            'subject' => 'Sent via Outlook',
            'body' => 'Already sent externally.',
            'log_only' => true,
        ])->assertRedirect();

        Mail::assertNothingSent();
        $this->assertSame(1, $member->commsLog()->count());
    }

    public function test_goals_outcomes_alerts_and_consents_store(): void
    {
        $staff = $this->staff();
        $member = Member::create(['first_name' => 'Amy', 'last_name' => 'Buckle']);

        $this->actingAs($staff)->post("/members/{$member->id}/goals", ['title' => 'Feed the chickens independently']);
        $goal = $member->goals()->first();

        $this->actingAs($staff)->post("/members/{$member->id}/outcomes", [
            'date' => today()->toDateString(),
            'outcome' => 'Fed the chickens with minimal prompting',
            'member_goal_id' => $goal->id,
        ]);

        $this->actingAs($staff)->put("/members/{$member->id}/goals/{$goal->id}", ['status' => 'achieved']);
        $this->assertNotNull($goal->fresh()->achieved_at);

        $this->actingAs($staff)->post("/members/{$member->id}/alerts", [
            'type' => 'allergy', 'text' => 'Nut allergy', 'severity' => 'red',
        ]);
        $this->assertSame('Nut allergy', $member->alerts()->first()->text);

        // Consents upsert on (member, type).
        $this->actingAs($staff)->post("/members/{$member->id}/consents", ['consent_type' => 'photos', 'granted' => true]);
        $this->actingAs($staff)->post("/members/{$member->id}/consents", ['consent_type' => 'photos', 'granted' => false]);
        $this->assertSame(1, $member->consents()->count());
        $this->assertFalse($member->consents()->first()->granted);
    }

    public function test_audit_log_records_creates_and_redacts_encrypted_changes(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);

        $this->actingAs($manager);
        $member = Member::create(['first_name' => 'Amy', 'last_name' => 'Buckle']);
        $member->update(['nhs_number' => '999 999 9999', 'town' => 'Bewdley']);

        $created = AuditLog::where('action', 'created')->where('subject_type', 'Member')->first();
        $this->assertNotNull($created);
        $this->assertSame($manager->id, $created->user_id);

        $updated = AuditLog::where('action', 'updated')->where('subject_type', 'Member')->first();
        $this->assertSame('[redacted]', $updated->changes['nhs_number']);
        $this->assertSame('Bewdley', $updated->changes['town']);

        $this->actingAs($manager)->get('/audit-log')->assertOk();
        $this->actingAs($this->staff())->get('/audit-log')->assertForbidden();
    }

    public function test_csv_import_previews_and_commits_members(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $csv = "first_name,last_name,preferred_name\nJohn,Smith,\nJane,Doe,JJ\n,Broken,\n";

        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('members.csv', $csv);

        $response = $this->actingAs($admin)->post('/import/preview', ['kind' => 'members', 'file' => $file]);
        $preview = $response->getSession()->get('import_preview');
        $this->assertSame(3, $preview['total']);
        $this->assertCount(1, $preview['problems']);

        $this->actingAs($admin)->post('/import/commit', ['kind' => 'members', 'csv' => $preview['csv']])
            ->assertRedirect();

        $this->assertSame(2, Member::count());
        $this->assertSame('JJ', Member::where('first_name', 'Jane')->first()->preferred_name);
    }

    public function test_reports_csv_exports_are_capability_gated(): void
    {
        Member::create(['first_name' => 'Amy', 'last_name' => 'Buckle']);
        $manager = User::factory()->create(['role' => 'manager']);

        $response = $this->actingAs($manager)->get('/reports/members.csv');
        $response->assertOk();
        $this->assertStringContainsString('Amy Buckle', $response->streamedContent());

        $this->actingAs($this->staff())->get('/reports/members.csv')->assertForbidden();
    }

    public function test_transport_payment_delete_restores_balance(): void
    {
        $staff = $this->staff();
        $member = Member::create(['first_name' => 'Amy', 'last_name' => 'Buckle']);
        $member->settings()->create(['transport_required' => true]);

        $this->actingAs($staff)->post("/transport/{$member->id}/pay", ['amount' => 10]);
        $payment = TransportLedgerEntry::where('type', 'payment')->first();

        $this->actingAs($staff)->delete("/transport/payments/{$payment->id}")->assertRedirect();
        $this->assertSame(0.0, TransportLedgerEntry::balanceFor($member->id));
    }

    public function test_overtime_rate_autofills_at_time_and_a_half(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $lucy = \App\Models\StaffRosterMember::create(['name' => 'Lucy Mills', 'active' => true]);

        $this->actingAs($manager)->post('/payroll/rates', [
            'key' => "roster:{$lucy->id}",
            'hourly_rate' => 12.71,
            'contracted_hours' => 20,
        ])->assertRedirect();

        $rate = $lucy->rates()->first();
        $this->assertSame(19.07, (float) $rate->overtime_rate);
        $this->assertSame(20.0, (float) $rate->contracted_hours);
    }
}
