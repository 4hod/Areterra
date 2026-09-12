<?php

namespace Tests\Feature\Sweep;

use App\Models\Animal;
use App\Models\Member;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;

class CoreRecordsSweepTest extends SweepTestCase
{
    public function test_members_create_update_and_bulk_status(): void
    {
        $r = $this->post('/members', [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'status' => 'active',
            'dob' => '1990-05-02',
            'phone' => '01905 123456',
            'email' => 'ada@example.test',
            'postcode' => 'WR1 1AA',
        ]);
        $this->assertWriteOk($r, 'members.store');
        $m = Member::where('last_name', 'Lovelace')->first();
        $this->assertNotNull($m, 'member was not created');

        $r = $this->put("/members/{$m->id}", [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'status' => 'on-leave',
            'town' => 'Malvern',
        ]);
        $this->assertWriteOk($r, 'members.update');
        $this->assertSame('on-leave', $m->fresh()->status);
        $this->assertSame('Malvern', $m->fresh()->town);

        $r = $this->put('/members/bulk/status', ['ids' => [$m->id], 'status' => 'archived']);
        $this->assertWriteOk($r, 'members.bulk-status');
        $this->assertSame('archived', $m->fresh()->status);

        $this->get('/members')->assertOk();
        $this->get("/members/{$m->id}")->assertOk();
        $this->get("/members/{$m->id}/history")->assertOk();
        $this->get("/members/{$m->id}/care-plan")->assertOk();
        $this->get("/members/{$m->id}/sar")->assertOk();
    }

    public function test_member_profile_sub_records_create_and_delete(): void
    {
        $m = $this->member();

        // Comms log
        $r = $this->post("/members/{$m->id}/comms", [
            'type' => 'phone', 'direction' => 'inbound',
            'subject' => 'Called about transport', 'summary' => 'Mum rang.',
            'date' => now()->toDateString(),
        ]);
        $this->assertWriteOk($r, 'comms.store');
        $comms = DB::table('comms_log')->where('member_id', $m->id)->first();
        $this->assertNotNull($comms, 'comms log not created');
        $this->assertWriteOk($this->delete("/members/{$m->id}/comms/{$comms->id}"), 'comms.destroy');
        $this->assertGone('comms_log', $comms->id, 'comms.destroy');

        // Contact
        $r = $this->post("/members/{$m->id}/contacts", [
            'name' => 'Jane Doe', 'role' => 'Social worker',
            'organisation' => 'County Council', 'email' => 'jane@example.test',
        ]);
        $this->assertWriteOk($r, 'contacts.store');
        $c = DB::table('member_contacts')->where('member_id', $m->id)->first();
        $this->assertNotNull($c, 'contact not created');
        $this->assertWriteOk($this->delete("/members/{$m->id}/contacts/{$c->id}"), 'contacts.destroy');
        $this->assertGone('member_contacts', $c->id, 'contacts.destroy');

        // Goal + update + outcome
        $r = $this->post("/members/{$m->id}/goals", [
            'title' => 'Feed the chickens independently',
            'description' => 'Build confidence around the birds.',
            'target_date' => now()->addMonths(3)->toDateString(),
        ]);
        $this->assertWriteOk($r, 'goals.store');
        $g = DB::table('member_goals')->where('member_id', $m->id)->first();
        $this->assertNotNull($g, 'goal not created');
        $this->assertWriteOk($this->put("/members/{$m->id}/goals/{$g->id}", ['status' => 'achieved']), 'goals.update');
        $this->assertSame('achieved', DB::table('member_goals')->find($g->id)->status);

        $r = $this->post("/members/{$m->id}/outcomes", [
            'date' => now()->toDateString(), 'outcome' => 'Fed them solo today.',
            'member_goal_id' => $g->id,
        ]);
        $this->assertWriteOk($r, 'outcomes.store');
        $this->assertNotNull(DB::table('member_outcomes')->where('member_id', $m->id)->first(), 'outcome not created');

        // Alert
        $r = $this->post("/members/{$m->id}/alerts", [
            'type' => 'allergy', 'text' => 'Severe nut allergy', 'severity' => 'red',
        ]);
        $this->assertWriteOk($r, 'alerts.store');
        $a = DB::table('member_alerts')->where('member_id', $m->id)->first();
        $this->assertNotNull($a, 'alert not created');
        $this->assertWriteOk($this->delete("/members/{$m->id}/alerts/{$a->id}"), 'alerts.destroy');
        $this->assertGone('member_alerts', $a->id, 'alerts.destroy');

        // Consent
        $r = $this->post("/members/{$m->id}/consents", [
            'consent_type' => 'photos', 'granted' => true, 'notes' => 'Signed on intake.',
        ]);
        $this->assertWriteOk($r, 'consents.store');
        $this->assertNotNull(DB::table('member_consents')->where('member_id', $m->id)->first(), 'consent not created');
    }

    public function test_care_records_abc_and_body_map(): void
    {
        $m = $this->member();

        $r = $this->post("/members/{$m->id}/abc", [
            'observed_at' => now()->toDateTimeString(),
            'antecedent' => 'Loud noise in the yard',
            'behaviour' => 'Withdrew to the quiet room',
            'consequence' => 'Settled after ten minutes',
            'wellbeing_score' => 3,
            'concern' => false,
        ]);
        $this->assertWriteOk($r, 'abc.store');
        $this->assertNotNull(DB::table('abc_observations')->where('member_id', $m->id)->first(), 'ABC not created');

        $r = $this->post("/members/{$m->id}/body-maps", [
            'markers' => [['view' => 'front', 'x' => 40.5, 'y' => 62.0, 'note' => 'Small graze']],
            'notes' => 'Noticed at handover.',
            'observed_at' => now()->toDateTimeString(),
        ]);
        $this->assertWriteOk($r, 'body-maps.store');
        $this->assertNotNull(DB::table('body_maps')->where('member_id', $m->id)->first(), 'body map not created');
    }

    public function test_animals_create_update_and_welfare_records(): void
    {
        $r = $this->post('/animals', [
            'name' => 'Pip', 'species' => 'Chinchilla', 'sex' => 'female',
            'status' => 'active', 'dob' => '2022-03-01',
        ]);
        $this->assertWriteOk($r, 'animals.store');
        $a = Animal::where('name', 'Pip')->first();
        $this->assertNotNull($a, 'animal not created');

        $this->assertWriteOk($this->put("/animals/{$a->id}", [
            'name' => 'Pip', 'species' => 'Chinchilla', 'status' => 'active',
            'care_requirements' => 'Dust bath twice weekly',
        ]), 'animals.update');
        $this->assertSame('Dust bath twice weekly', $a->fresh()->care_requirements);

        // Welfare check
        $r = $this->post("/animals/{$a->id}/welfare-checks", [
            'status' => 'green', 'notes' => 'Bright and active', 'fed' => true,
        ]);
        $this->assertWriteOk($r, 'welfare.store');
        $this->assertNotNull(DB::table('welfare_checks')->where('animal_id', $a->id)->first(), 'welfare check not created');

        // Species-wide round
        $r = $this->post('/welfare-checks/species', [
            'species' => 'Chinchilla',
            'checks' => [['animal_id' => $a->id, 'status' => 'amber', 'notes' => 'Eating less', 'fed' => true]],
        ]);
        $this->assertWriteOk($r, 'welfare.species');

        // Daily monitoring
        $r = $this->post("/animals/{$a->id}/monitoring", [
            'monitor_date' => now()->toDateString(),
            'weight_grams' => 540, 'body_condition' => 3, 'appetite' => 'good',
        ]);
        $this->assertWriteOk($r, 'monitoring.store');
        $this->assertNotNull(DB::table('daily_monitoring')->where('animal_id', $a->id)->first(), 'monitoring not created');

        // Vet record
        $r = $this->post("/animals/{$a->id}/vet-records", [
            'visit_date' => now()->toDateString(), 'reason' => 'Routine check',
            'vet_name' => 'Mr Vet', 'next_due_date' => now()->addYear()->toDateString(),
        ]);
        $this->assertWriteOk($r, 'vet-records.store');
        $this->assertNotNull(DB::table('vet_records')->where('animal_id', $a->id)->first(), 'vet record not created');

        $this->get('/animals')->assertOk();
        $this->get("/animals/{$a->id}")->assertOk();
        $this->get('/monitoring')->assertOk();
    }

    public function test_activities_create(): void
    {
        $r = $this->post('/activities', [
            'title' => 'Animal handling session',
            'activity_date' => now()->toDateString(),
            'start_time' => '10:30',
            'description' => 'Small group in the barn.',
        ]);
        $this->assertWriteOk($r, 'activities.store');
        $this->assertNotNull(DB::table('activities')->where('title', 'Animal handling session')->first(), 'activity not created');
        $this->get('/activities')->assertOk();
        $this->get('/weekly-planner')->assertOk();
        $this->get('/weekly-planner/print')->assertOk();
    }

    public function test_vehicles_and_defects(): void
    {
        $r = $this->post('/vehicles', [
            'registration' => 'AB12CDE', 'make_model' => 'Ford Transit',
            'mot_due' => now()->addMonths(6)->toDateString(),
        ]);
        $this->assertWriteOk($r, 'vehicles.store');
        $v = Vehicle::where('registration', 'AB12CDE')->first();
        $this->assertNotNull($v, 'vehicle not created');

        $this->assertWriteOk($this->put("/vehicles/{$v->id}", [
            'notes' => 'Rear door sticks', 'active' => true,
        ]), 'vehicles.update');
        $this->assertSame('Rear door sticks', $v->fresh()->notes);

        $r = $this->post("/vehicles/{$v->id}/defects", [
            'description' => 'Nearside mirror cracked', 'severity' => 'minor',
        ]);
        $this->assertWriteOk($r, 'defects.store');
        $d = DB::table('vehicle_defects')->where('vehicle_id', $v->id)->first();
        $this->assertNotNull($d, 'defect not created');

        $this->assertWriteOk($this->post("/defects/{$d->id}/resolve"), 'defects.resolve');
        $this->assertNotNull(DB::table('vehicle_defects')->find($d->id)->resolved_at, 'defect not resolved');

        $this->get('/vehicles')->assertOk();
    }
}
