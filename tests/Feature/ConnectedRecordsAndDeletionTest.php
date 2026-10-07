<?php

namespace Tests\Feature;

use App\Models\Animal;
use App\Models\Certificate;
use App\Models\Document;
use App\Models\Member;
use App\Models\PortfolioItem;
use App\Models\RiskAssessment;
use App\Models\Task;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ConnectedRecordsAndDeletionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'administrator']);
        $this->actingAs($this->admin);
    }

    public function test_certificate_and_its_portfolio_entry_can_be_removed_together(): void
    {
        $member = Member::create(['first_name' => 'Amy', 'last_name' => 'Buckle', 'status' => 'active']);

        $this->post("/members/{$member->id}/certificates", [
            'title' => 'Animal Care Award', 'description' => 'Completed the programme.',
            'issued_on' => today()->toDateString(),
        ])->assertSessionHas('success');

        $certificate = Certificate::firstOrFail();
        $itemId = $certificate->portfolio_item_id;

        $this->delete("/members/{$member->id}/certificates/{$certificate->id}")
            ->assertRedirect("/members/{$member->id}/portfolio");

        $this->assertSoftDeleted('certificates', ['id' => $certificate->id]);
        $this->assertSoftDeleted('portfolio_items', ['id' => $itemId]);
    }

    public function test_ordinary_portfolio_item_can_be_removed(): void
    {
        $member = Member::create(['first_name' => 'Amy', 'last_name' => 'Buckle', 'status' => 'active']);
        $item = PortfolioItem::create([
            'member_id' => $member->id, 'type' => 'achievement', 'title' => 'First project',
            'created_by' => $this->admin->id,
        ]);

        $this->delete("/members/{$member->id}/portfolio-items/{$item->id}")->assertSessionHas('success');
        $this->assertSoftDeleted('portfolio_items', ['id' => $item->id]);
    }

    public function test_document_can_be_linked_to_a_vehicle_and_soft_deleted(): void
    {
        Storage::fake('local');
        $vehicle = Vehicle::create(['registration' => 'AR26 HUB']);

        $this->post('/documents', [
            'title' => 'Minibus insurance', 'file' => UploadedFile::fake()->create('insurance.pdf', 20, 'application/pdf'),
            'related_type' => 'vehicle', 'related_id' => $vehicle->id,
        ])->assertSessionHas('success');

        $document = Document::firstOrFail();
        $this->assertTrue($document->attachable->is($vehicle));

        $this->delete("/documents/{$document->id}")->assertSessionHas('success');
        $this->assertSoftDeleted('documents', ['id' => $document->id]);
        Storage::assertExists($document->file_path);
    }

    public function test_only_unsigned_draft_risk_assessments_can_be_deleted(): void
    {
        $draft = RiskAssessment::create([
            'title' => 'Draft', 'category' => 'site', 'likelihood' => 1, 'severity' => 1,
            'status' => 'draft', 'created_by' => $this->admin->id,
        ]);
        $active = RiskAssessment::create([
            'title' => 'Signed', 'category' => 'site', 'likelihood' => 2, 'severity' => 2,
            'status' => 'active', 'signed_off_by' => $this->admin->id, 'signed_off_at' => now(),
            'created_by' => $this->admin->id,
        ]);

        $this->delete("/risk-assessments/{$draft->id}")->assertSessionHas('success');
        $this->assertSoftDeleted('risk_assessments', ['id' => $draft->id]);
        $this->delete("/risk-assessments/{$active->id}")->assertStatus(422);
        $this->assertDatabaseHas('risk_assessments', ['id' => $active->id, 'deleted_at' => null]);
    }

    public function test_tasks_and_incidents_are_attached_to_their_real_records(): void
    {
        $member = Member::create(['first_name' => 'Amy', 'last_name' => 'Buckle', 'status' => 'active']);
        $animal = Animal::create(['name' => 'Rico', 'species' => 'Rabbit', 'status' => 'active']);

        $this->post('/tasks', [
            'title' => 'Review support plan', 'priority' => 'high',
            'related_type' => 'member', 'related_id' => $member->id,
        ])->assertSessionHas('success');
        $task = Task::firstOrFail();
        $this->assertTrue($task->taskable->is($member));

        $this->post('/incidents', [
            'title' => 'Minor scratch', 'occurred_at' => now()->toDateTimeString(),
            'description' => 'Observed and cleaned.', 'severity' => 'minor',
            'follow_up_required' => true,
            'related_type' => 'animal', 'related_id' => $animal->id,
        ])->assertSessionHas('success');
        $this->assertDatabaseHas('incidents', [
            'subject_type' => $animal->getMorphClass(), 'subject_id' => $animal->id,
        ]);
        $this->assertTrue(Task::where('taskable_type', $animal->getMorphClass())->firstOrFail()->taskable->is($animal));

        $this->get("/tasks?about=member&id={$member->id}")->assertOk();
        $this->get("/incidents?about=animal&id={$animal->id}")->assertOk();

        $this->delete("/tasks/{$task->id}")->assertSessionHas('success');
        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
    }
}
