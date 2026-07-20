<?php

namespace Tests\Feature;

use App\Models\ComplianceItem;
use App\Models\Grant;
use App\Models\Member;
use App\Models\MemberInvoice;
use App\Models\Referral;
use App\Models\SafeguardingConcern;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class Phase4Test extends TestCase
{
    use RefreshDatabase;

    public function test_end_of_day_concern_auto_creates_safeguarding_entry(): void
    {
        Notification::fake();
        $staff = User::factory()->create(['role' => 'staff']);
        $member = Member::create(['first_name' => 'Amy', 'last_name' => 'Buckle']);

        $this->actingAs($staff)->post("/end-of-day/{$member->id}", [
            'end_mood' => 'sad',
            'concern' => true,
            'concern_detail' => 'Withdrawn today',
        ])->assertRedirect();

        $concern = SafeguardingConcern::first();
        $this->assertNotNull($concern);
        $this->assertSame('end_of_day', $concern->source);
        $this->assertSame($member->id, $concern->member_id);
        $this->assertSame('Withdrawn today', $concern->details);

        // Editing the record again must not duplicate the entry.
        $this->actingAs($staff)->post("/end-of-day/{$member->id}", [
            'end_mood' => 'neutral',
            'concern' => true,
            'concern_detail' => 'Withdrawn today',
        ]);
        $this->assertSame(1, SafeguardingConcern::count());
    }

    public function test_safeguarding_requires_capability_and_password_confirmation(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $lead = User::factory()->create(['role' => 'safeguarding_lead']);

        $this->actingAs($staff)->get('/safeguarding')->assertForbidden();

        // The lead has the capability but must re-confirm their password first.
        $this->actingAs($lead)->get('/safeguarding')->assertRedirect('/confirm-password');

        $this->actingAs($lead)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get('/safeguarding')
            ->assertOk();
    }

    public function test_sent_invoice_past_due_reads_as_overdue(): void
    {
        $member = Member::create(['first_name' => 'Amy', 'last_name' => 'Buckle']);

        $invoice = MemberInvoice::create([
            'member_id' => $member->id,
            'qb_reference' => 'INV-0042',
            'amount' => 100,
            'invoice_date' => today()->subMonth(),
            'due_date' => today()->subDay(),
            'status' => 'sent',
        ]);

        $this->assertSame('overdue', $invoice->effectiveStatus());

        $invoice->update(['status' => 'paid', 'paid_date' => today()]);
        $this->assertSame('paid', $invoice->fresh()->effectiveStatus());
    }

    public function test_public_referral_form_submits_without_auth_and_honeypot_rejects_bots(): void
    {
        $this->post('/refer', [
            'referrer_name' => 'Jane Social Worker',
            'person_name' => 'John Smith',
            'details' => 'Looking for weekday placements.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(1, Referral::count());

        $this->post('/refer', [
            'referrer_name' => 'Bot',
            'person_name' => 'Bot Person',
            'website' => 'spam.example',
        ])->assertSessionHasErrors('website');

        $this->assertSame(1, Referral::count());
    }

    public function test_accepting_referral_creates_member_profile(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $referral = Referral::create(['referrer_name' => 'Jane', 'person_name' => 'John Smith']);

        $this->actingAs($manager)->put("/referrals/{$referral->id}/review", ['status' => 'accepted'])
            ->assertRedirect();

        $member = Member::where('first_name', 'John')->where('last_name', 'Smith')->first();
        $this->assertNotNull($member);
        $this->assertSame('inactive', $member->status);
        $this->assertSame('accepted', $referral->fresh()->status);
    }

    public function test_audit_flags_stale_referrals_and_overdue_compliance(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);

        $stale = Referral::create(['referrer_name' => 'Jane', 'person_name' => 'John Smith']);
        $stale->timestamps = false;
        $stale->created_at = now()->subDays(8);
        $stale->save();

        ComplianceItem::create(['title' => 'Renew insurance', 'due_date' => today()->subWeek()]);

        $response = $this->actingAs($manager)->get('/audit')->assertOk();
        $messages = collect($response->viewData('page')['props']['findings'])->pluck('message');

        $this->assertTrue($messages->contains(fn ($m) => str_contains($m, 'John Smith') && str_contains($m, 'pending')));
        $this->assertTrue($messages->contains(fn ($m) => str_contains($m, 'Renew insurance')));
    }

    public function test_grant_tracks_spend_against_amount(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $grant = Grant::create(['title' => 'Animal welfare fund', 'funder' => 'Big Lottery', 'amount' => 5000]);

        $this->actingAs($manager)->post("/finance/grants/{$grant->id}/expenditures", [
            'description' => 'Macaw aviary repairs',
            'amount' => 1200.50,
            'spent_date' => today()->toDateString(),
        ])->assertRedirect();

        $this->assertSame(1200.50, $grant->fresh()->spent());
    }

    public function test_finance_and_settings_are_capability_gated(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $manager = User::factory()->create(['role' => 'manager']);
        $admin = User::factory()->create(['role' => 'administrator']);

        $this->actingAs($staff)->get('/finance')->assertForbidden();
        $this->actingAs($manager)->get('/finance')->assertOk();

        $this->actingAs($manager)->get('/settings')->assertForbidden();
        $this->actingAs($admin)->get('/settings')->assertOk();
    }

    public function test_abc_and_body_map_store_against_member(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $member = Member::create(['first_name' => 'Amy', 'last_name' => 'Buckle']);

        $this->actingAs($staff)->post("/members/{$member->id}/abc", [
            'observed_at' => now()->toDateTimeString(),
            'behaviour' => 'Became distressed during feeding',
            'antecedent' => 'Loud noise from the road',
            'wellbeing_score' => 3,
        ])->assertRedirect();

        $this->actingAs($staff)->post("/members/{$member->id}/body-maps", [
            'markers' => [['view' => 'front', 'x' => 45.5, 'y' => 30.2, 'note' => 'Small bruise']],
            'notes' => 'Noticed on arrival',
        ])->assertRedirect();

        $this->assertSame(1, $member->abcObservations()->count());
        $this->assertSame('Became distressed during feeding', $member->abcObservations()->first()->behaviour);
        $this->assertCount(1, $member->bodyMaps()->first()->markers);
    }
}
