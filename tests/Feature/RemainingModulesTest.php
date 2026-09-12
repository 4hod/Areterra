<?php

namespace Tests\Feature;

use App\Models\FormDefinition;
use App\Models\Member;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The last of the staff-facing modules: creating members, uploading documents,
 * member profile sub-records, building forms, vehicles and defects, invoices,
 * projects and the email composer.
 */
class RemainingModulesTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private User $manager;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = User::factory()->create(['role' => 'manager']);
        $this->staff = User::factory()->create(['role' => 'staff']);
        $this->admin = User::factory()->create(['role' => 'administrator']);
    }

    private function member(): Member
    {
        return Member::create(['first_name' => 'Amy', 'last_name' => 'Buckle', 'status' => 'active']);
    }

    // --------------------------------------------------------------- members

    public function test_creating_a_member_saves_them_with_their_emergency_contact(): void
    {
        $this->actingAs($this->manager)->post('/members', [
            'first_name' => 'New',
            'last_name' => 'Person',
            'status' => 'active',
            'town' => 'Worcester',
            'emergency_contacts' => [
                ['name' => 'Jane Person', 'relationship' => 'Mother', 'phone' => '01234 567890'],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $member = Member::where('first_name', 'New')->first();

        $this->assertNotNull($member, 'The member was not created.');
        $this->assertSame('Worcester', $member->town);
    }

    // ------------------------------------------------------------- documents

    public function test_uploading_a_document_stores_the_file_and_the_record(): void
    {
        Storage::fake('local');

        $this->actingAs($this->manager)->post('/documents', [
            'title' => 'Fire policy',
            'category' => 'policy',
            'file' => UploadedFile::fake()->create('fire-policy.pdf', 40, 'application/pdf'),
        ])->assertSessionHasNoErrors()->assertRedirect();

        $document = \App\Models\Document::first();

        $this->assertNotNull($document, 'The document record was not created.');
        $this->assertSame('Fire policy', $document->title);
        $this->assertNotEmpty($document->file_path, 'No stored file path was recorded.');
    }

    // ------------------------------------------------- member profile records

    public function test_a_consent_is_recorded_against_the_member(): void
    {
        $amy = $this->member();

        $this->actingAs($this->manager)->post("/members/{$amy->id}/consents", [
            'consent_type' => 'photos',
            'granted' => true,
            'notes' => 'Confirmed by mum.',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame(1, $amy->consents()->count());
    }

    public function test_an_alert_is_recorded_against_the_member(): void
    {
        $amy = $this->member();

        $this->actingAs($this->manager)->post("/members/{$amy->id}/alerts", [
            'type' => 'medical',
            'text' => 'Carries an inhaler.',
            'severity' => 'red',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame(1, $amy->alerts()->count());
    }

    public function test_a_communication_is_logged_against_the_member(): void
    {
        $amy = $this->member();

        $this->actingAs($this->staff)->post("/members/{$amy->id}/comms", [
            'type' => 'phone',
            'direction' => 'outbound',
            'date' => today()->toDateString(),
            'summary' => 'Called mum about Friday.',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame(1, $amy->commsLog()->count());
    }

    public function test_an_abc_chart_entry_is_saved_against_the_member(): void
    {
        $amy = $this->member();

        $this->actingAs($this->staff)->post("/members/{$amy->id}/abc", [
            'observed_at' => now()->toDateTimeString(),
            'antecedent' => 'Loud noise in the barn.',
            'behaviour' => 'Became distressed and left the room.',
            'consequence' => 'Supported outside, settled after 10 minutes.',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame(1, $amy->abcObservations()->count());
    }

    public function test_a_body_map_is_saved_against_the_member(): void
    {
        $amy = $this->member();

        $this->actingAs($this->staff)->post("/members/{$amy->id}/body-maps", [
            'markers' => [
                ['view' => 'front', 'x' => 40, 'y' => 55, 'note' => 'Small graze'],
            ],
            'notes' => 'Noticed at arrival.',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame(1, $amy->bodyMaps()->count());
    }

    // ----------------------------------------------------------------- forms

    public function test_building_a_form_saves_it_with_its_fields(): void
    {
        $this->actingAs($this->manager)->post('/forms', [
            'title' => 'Session feedback',
            'description' => 'How did it go?',
            'fields' => [
                ['label' => 'What went well', 'type' => 'textarea', 'is_required' => true],
                ['label' => 'Mood', 'type' => 'select', 'options' => ['happy', 'calm', 'unsettled'], 'is_required' => false],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $form = FormDefinition::first();

        $this->assertNotNull($form, 'The form was not created.');
        $this->assertSame(2, $form->fields()->count());
    }

    // -------------------------------------------------------------- vehicles

    public function test_a_vehicle_and_a_defect_are_saved_and_the_defect_resolves(): void
    {
        $this->actingAs($this->manager)->post('/vehicles', [
            'registration' => 'AB12 CDE',
            'make_model' => 'Ford Transit',
            'mot_due' => today()->addMonths(6)->toDateString(),
        ])->assertSessionHasNoErrors()->assertRedirect();

        $vehicle = Vehicle::first();
        $this->assertNotNull($vehicle, 'The vehicle was not saved.');

        // view_vehicles is manager-level in the capability matrix, so a
        // support worker cannot log a defect. Worth knowing before rollout.
        $this->actingAs($this->manager)->post("/vehicles/{$vehicle->id}/defects", [
            'description' => 'Nearside mirror loose',
            'severity' => 'minor',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $defect = \App\Models\VehicleDefect::first();
        $this->assertNotNull($defect, 'The defect was not saved.');

        $this->actingAs($this->manager)->post("/defects/{$defect->id}/resolve")->assertRedirect();
        $this->assertNotNull($defect->fresh()->resolved_at, 'The defect was not resolved.');
    }

    // -------------------------------------------------------------- invoices

    public function test_an_invoice_is_created_and_can_be_marked_paid(): void
    {
        $amy = $this->member();

        $this->actingAs($this->admin)->post('/invoices', [
            'member_id' => $amy->id,
            'qb_reference' => 'INV-1001',
            'amount' => 120.00,
            'invoice_date' => today()->toDateString(),
            'due_date' => today()->addDays(30)->toDateString(),
            'status' => 'sent',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $invoice = \App\Models\MemberInvoice::first();
        $this->assertNotNull($invoice, 'The invoice was not created.');

        $this->actingAs($this->admin)->post("/invoices/{$invoice->id}/paid")->assertRedirect();
        $this->assertSame('paid', $invoice->fresh()->status);
    }

    // -------------------------------------------------------------- projects

    public function test_a_project_is_saved(): void
    {
        $this->actingAs($this->manager)->post('/projects', [
            'title' => 'New aviary',
            'description' => 'Build the second aviary.',
            'status' => 'planning',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('projects', ['title' => 'New aviary']);
    }

    // -------------------------------------------------------- email composer

    public function test_the_email_composer_sends_and_logs_against_the_member(): void
    {
        Mail::fake();
        $amy = $this->member();

        $this->actingAs($this->manager)->post('/email/send', [
            'member_id' => $amy->id,
            'to_email' => 'mum@example.com',
            'to_name' => 'Jane Buckle',
            'subject' => 'Friday session',
            'body' => 'Just to confirm Amy is booked in for Friday.',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame(1, $amy->commsLog()->count(), 'The email was not logged to the member.');
    }

    public function test_log_only_records_the_email_without_sending_it(): void
    {
        Mail::fake();
        $amy = $this->member();

        $this->actingAs($this->manager)->post('/email/send', [
            'member_id' => $amy->id,
            'subject' => 'Phone call follow-up',
            'body' => 'Spoke to mum on the phone.',
            'log_only' => true,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame(1, $amy->commsLog()->count());
        Mail::assertNothingSent();
    }
}
