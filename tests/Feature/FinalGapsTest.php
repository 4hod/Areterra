<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Member;
use App\Models\Policy;
use App\Models\Recognition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** The last few write routes: reads, approvals, likes, photos, bulk actions. */
class FinalGapsTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        User::factory()->create(['role' => 'manager']);
        $this->staff = User::factory()->create(['role' => 'staff']);
        $this->admin = User::factory()->create(['role' => 'administrator']);
    }

    public function test_marking_an_announcement_read_records_it_for_that_user(): void
    {
        $announcement = Announcement::create([
            'title' => 'Team meeting', 'body' => '9am Friday', 'user_id' => $this->admin->id,
        ]);

        $this->actingAs($this->staff)->post("/announcements/{$announcement->id}/read")->assertRedirect();

        $this->assertDatabaseHas('announcement_reads', [
            'announcement_id' => $announcement->id,
            'user_id' => $this->staff->id,
        ]);
    }

    public function test_approving_a_policy_marks_it_approved(): void
    {
        $policy = Policy::create([
            'title' => 'Lone working', 'body' => 'Text.', 'version' => '1.0',
            'status' => 'draft', 'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)->post("/policies/{$policy->id}/approve")->assertRedirect();

        $this->assertNotNull($policy->fresh()->approved_at, 'The policy was not approved.');
    }

    public function test_liking_a_shoutout_is_recorded_and_toggles_off(): void
    {
        $recognition = Recognition::create([
            'recipient' => 'Lucy', 'message' => 'Great work', 'user_id' => $this->admin->id,
        ]);

        $this->actingAs($this->staff)->post("/recognition/{$recognition->id}/like")->assertRedirect();
        $this->assertSame(1, $recognition->likes()->count());

        $this->actingAs($this->staff)->post("/recognition/{$recognition->id}/like")->assertRedirect();
        $this->assertSame(0, $recognition->likes()->count(), 'Liking again should remove the like.');
    }

    public function test_a_member_photo_uploads_and_is_stored_on_the_member(): void
    {
        // Fake image generation needs GD; skip cleanly where it isn't built in
        // rather than reporting a failure that isn't about the Hub.
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD extension not available.');
        }

        Storage::fake('public');
        $amy = Member::create(['first_name' => 'Amy', 'last_name' => 'Buckle', 'status' => 'active']);

        $this->actingAs($this->admin)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post("/members/{$amy->id}/photo", [
            'photo' => UploadedFile::fake()->image('amy.jpg'),
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertNotEmpty($amy->fresh()->photo_path, 'No photo path was stored.');
    }

    public function test_an_outcome_is_recorded_against_a_member_goal(): void
    {
        $amy = Member::create(['first_name' => 'Amy', 'last_name' => 'Buckle', 'status' => 'active']);
        $goal = $amy->goals()->create(['title' => 'Handle a bird unaided']);

        $this->actingAs($this->staff)->post("/members/{$amy->id}/outcomes", [
            'date' => today()->toDateString(),
            'outcome' => 'Held Rico for the first time.',
            'member_goal_id' => $goal->id,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame(1, $amy->outcomes()->count());
        $this->assertSame($goal->id, $amy->outcomes()->first()->member_goal_id);
    }

    public function test_bulk_marking_invoices_paid_updates_all_of_them(): void
    {
        $amy = Member::create(['first_name' => 'Amy', 'last_name' => 'Buckle', 'status' => 'active']);

        $ids = collect(['INV-1', 'INV-2'])->map(fn ($ref) => \App\Models\MemberInvoice::create([
            'member_id' => $amy->id, 'qb_reference' => $ref, 'amount' => 50,
            'invoice_date' => today(), 'status' => 'sent',
        ])->id)->all();

        $this->actingAs($this->admin)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post('/invoices/bulk/paid', ['ids' => $ids])
            ->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame(0, \App\Models\MemberInvoice::where('status', '!=', 'paid')->count());
    }

    public function test_marking_a_document_read_records_it(): void
    {
        Storage::fake('local');
        $document = \App\Models\Document::create([
            'title' => 'Fire policy', 'category' => 'policy',
            'file_path' => 'docs/fire.pdf', 'original_name' => 'fire.pdf',
            'requires_read' => true, 'uploaded_by' => $this->admin->id,
        ]);

        $this->actingAs($this->staff)->post("/documents/{$document->id}/read")->assertRedirect();

        $this->assertDatabaseHas('document_reads', [
            'document_id' => $document->id,
            'user_id' => $this->staff->id,
        ]);
    }
}
