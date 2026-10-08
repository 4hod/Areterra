<?php

namespace Tests\Feature;

use App\Models\EndOfDayRecord;
use App\Models\Member;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_photos_are_private_and_permission_checked(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $manager = User::factory()->create(['role' => 'manager']);
        $volunteer = User::factory()->create(['role' => 'volunteer']);
        $member = Member::create(['first_name' => 'Test', 'last_name' => 'Person']);

        $this->actingAs($manager)->post("/members/{$member->id}/photo", [
            'photo' => UploadedFile::fake()->image('member.jpg'),
        ])->assertRedirect();

        $path = $member->fresh()->getRawOriginal('photo_path');
        $this->assertStringNotContainsString('/storage/', $path);
        Storage::disk('local')->assertExists($path);
        Storage::disk('public')->assertMissing($path);

        $this->actingAs($manager)->get(route('media.members.photo', $member))->assertOk();
        $this->actingAs($volunteer)->get(route('media.members.photo', $member))->assertForbidden();
        auth()->logout();
        $this->get(route('media.members.photo', $member))->assertRedirect('/login');
    }

    public function test_private_media_uses_the_configured_object_storage_disk(): void
    {
        Config::set('filesystems.default', 's3');
        Storage::fake('s3');
        Storage::fake('public');
        $manager = User::factory()->create(['role' => 'manager']);
        $member = Member::create(['first_name' => 'Object', 'last_name' => 'Storage']);

        $this->actingAs($manager)->post("/members/{$member->id}/photo", [
            'photo' => UploadedFile::fake()->image('member.jpg'),
        ])->assertRedirect();

        $path = $member->fresh()->getRawOriginal('photo_path');
        Storage::disk('s3')->assertExists($path);
        Storage::disk('public')->assertMissing($path);
        $this->actingAs($manager)->get(route('media.members.photo', $member))->assertOk();
    }

    public function test_private_media_can_be_migrated_between_disks(): void
    {
        Storage::fake('local');
        Storage::fake('s3');
        $member = Member::create([
            'first_name' => 'Move',
            'last_name' => 'Media',
            'photo_path' => 'member-photos/example.jpg',
        ]);
        Storage::disk('local')->put('member-photos/example.jpg', 'image-bytes');

        $this->artisan('hub:migrate-private-media', [
            '--source' => 'local',
            '--target' => 's3',
            '--commit' => true,
        ])->assertSuccessful();

        Storage::disk('s3')->assertExists('member-photos/example.jpg');
        Storage::disk('local')->assertMissing('member-photos/example.jpg');
        $this->assertSame('member-photos/example.jpg', $member->fresh()->getRawOriginal('photo_path'));
    }

    public function test_end_of_day_photos_are_only_served_through_authorised_route(): void
    {
        Storage::fake('local');
        $staff = User::factory()->create(['role' => 'staff']);
        $member = Member::create(['first_name' => 'Test', 'last_name' => 'Person']);
        Storage::disk('local')->put('end-of-day-photos/test.jpg', 'image-bytes');
        $record = EndOfDayRecord::create([
            'member_id' => $member->id,
            'date' => today(),
            'user_id' => $staff->id,
            'photos' => ['end-of-day-photos/test.jpg'],
        ]);

        $this->actingAs($staff)
            ->get(route('media.end-of-day.photo', [$member, $record, 0]))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');

        $other = Member::create(['first_name' => 'Other', 'last_name' => 'Person']);
        $this->actingAs($staff)
            ->get(route('media.end-of-day.photo', [$other, $record, 0]))
            ->assertNotFound();
    }

    public function test_sensitive_client_values_are_application_encrypted(): void
    {
        $user = User::factory()->create(['role' => 'staff']);
        $member = Member::create([
            'first_name' => 'Test',
            'last_name' => 'Person',
            'phone' => '01562 000000',
            'address_line1' => 'Sensitive address',
        ]);
        $record = EndOfDayRecord::create([
            'member_id' => $member->id,
            'date' => today(),
            'user_id' => $user->id,
            'medication_notes' => 'Sensitive medication note',
        ]);

        $rawMember = DB::table('members')->find($member->id);
        $rawRecord = DB::table('end_of_day_records')->find($record->id);
        $this->assertNotSame('01562 000000', $rawMember->phone);
        $this->assertNotSame('Sensitive address', $rawMember->address_line1);
        $this->assertNotSame('Sensitive medication note', $rawRecord->medication_notes);
        $this->assertSame('01562 000000', $member->fresh()->phone);
        $this->assertSame('Sensitive medication note', $record->fresh()->medication_notes);
    }

    public function test_staff_cannot_change_someone_elses_task(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $staff = User::factory()->create(['role' => 'staff']);
        $colleague = User::factory()->create(['role' => 'staff']);
        $task = Task::create([
            'title' => 'Private client task',
            'priority' => 'high',
            'due_date' => today()->subDay(),
            'assigned_to' => $colleague->id,
            'created_by' => $manager->id,
        ]);

        $this->actingAs($staff)->post("/tasks/{$task->id}/complete")->assertForbidden();
        $this->assertNull($task->fresh()->completed_at);
        $this->actingAs($staff)->get('/tasks')->assertInertia(fn ($page) => $page->has('tasks', 0));
        $this->actingAs($staff)->get('/')->assertInertia(fn ($page) => $page->has('needsAttention', 0));

        $this->actingAs($manager)->post("/tasks/{$task->id}/complete")->assertRedirect();
        $this->assertNotNull($task->fresh()->completed_at);
    }

    public function test_password_login_can_be_disabled_for_microsoft_mfa_rollout(): void
    {
        Config::set('security.require_microsoft_sso', true);
        $user = User::factory()->create(['password' => 'correct-password-123']);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'correct-password-123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
