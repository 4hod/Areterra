<?php

namespace Tests\Feature\Sweep;

use App\Models\Announcement;
use App\Notifications\AnnouncementPosted;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * The last few routes not reached by the module sweeps. Everything here is
 * self-contained; the WordPress sync endpoints and the push "send a test"
 * route are deliberately excluded because they call out to third parties.
 */
class RemainingRoutesSweepTest extends SweepTestCase
{
    public function test_account_photo_upload_and_removal(): void
    {
        Storage::fake('public');

        $this->assertWriteOk($this->post('/account/photo', [
            'photo' => UploadedFile::fake()->image('me.jpg', 400, 400),
        ]), 'account.photo');
        $this->assertNotNull($this->admin->fresh()->photo_path, 'photo not attached to the user');

        $this->assertWriteOk($this->delete('/account/photo'), 'account.photo.remove');
        $this->assertNull($this->admin->fresh()->photo_path, 'photo not cleared');
    }

    public function test_password_confirmation_gate(): void
    {
        $this->get('/confirm-password')->assertOk();

        // Wrong password must not open the gate.
        $this->post('/confirm-password', ['password' => 'not-the-password']);
        $this->assertNull(session('auth.password_confirmed_at'), 'gate opened on a wrong password');

        // UserFactory's default password.
        $this->assertWriteOk($this->post('/confirm-password', ['password' => 'password']), 'password.confirm');
        $this->assertNotNull(session('auth.password_confirmed_at'), 'gate did not open on the correct password');
    }

    public function test_email_composer_logs_to_comms_and_saves_a_template(): void
    {
        $m = $this->member();

        $this->assertWriteOk($this->post('/email/send', [
            'member_id' => $m->id,
            'subject' => 'Welcome to Areterra',
            'body' => 'We look forward to seeing you.',
            'log_only' => true,
            'save_template' => true,
            'template_name' => 'Welcome letter',
        ]), 'email.send');

        $this->assertNotNull(
            DB::table('comms_log')->where('member_id', $m->id)->first(),
            'email not logged to the comms timeline'
        );

        $t = DB::table('email_templates')->where('name', 'Welcome letter')->first();
        $this->assertNotNull($t, 'template not saved');

        $this->assertWriteOk($this->delete("/email/templates/{$t->id}"), 'email.templates.destroy');
        $this->assertGone('email_templates', $t->id, 'email.templates.destroy');
    }

    public function test_push_subscription_and_notification_read(): void
    {
        $this->assertWriteOk($this->post('/push-subscriptions', [
            'endpoint' => 'https://push.example.test/endpoint/abc123',
            'keys' => ['p256dh' => 'test-p256dh-key', 'auth' => 'test-auth-key'],
        ]), 'push.subscribe');
        $this->assertNotNull(DB::table('push_subscriptions')->first(), 'push subscription not stored');

        $announcement = Announcement::create([
            'title' => 'Test notification',
            'body' => 'Something happened worth knowing about.',
            'user_id' => $this->admin->id,
        ]);
        $this->admin->notify(new AnnouncementPosted($announcement));
        $n = DB::table('notifications')->first();
        $this->assertNotNull($n, 'notification not stored');

        $this->assertWriteOk($this->post("/notifications/{$n->id}/read"), 'notifications.read');
        $this->assertNotNull(DB::table('notifications')->find($n->id)->read_at, 'notification not marked read');
    }
}
