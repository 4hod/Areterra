<?php

namespace Tests\Feature\Sweep;

use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Base for the full-module sweep. Every test drives real HTTP requests through
 * the whole middleware stack as an administrator, then asserts on the database.
 */
abstract class SweepTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'administrator',
            'name' => 'Sweep Admin',
            'email' => 'sweep-admin@example.test',
        ]);

        $this->actingAs($this->admin);
    }

    protected function member(array $attrs = []): Member
    {
        return Member::create(array_merge([
            'first_name' => 'Sweep',
            'last_name' => 'Member'.random_int(1000, 9999),
            'status' => 'active',
        ], $attrs));
    }

    /** A member scheduled to attend (and be transported) today. */
    protected function scheduledMember(array $attrs = []): Member
    {
        $m = $this->member($attrs);
        $m->settings()->create([
            'transport_required' => true,
            'attendance_days' => [today()->isoWeekday()],
        ]);

        return $m;
    }

    /** Assert a record is gone — either hard-deleted or soft-deleted. */
    protected function assertGone(string $table, int $id, string $label): void
    {
        $row = \Illuminate\Support\Facades\DB::table($table)->find($id);
        if ($row === null) {
            $this->assertTrue(true);

            return;
        }
        $this->assertNotNull(
            $row->deleted_at ?? null,
            "$label: record $table#$id still present and not soft-deleted"
        );
    }

    /** Assert a write route succeeded (redirect or 2xx), surfacing validation errors if not. */
    protected function assertWriteOk(\Illuminate\Testing\TestResponse $r, string $label): void
    {
        $status = $r->getStatusCode();

        if ($status === 302) {
            $errors = session('errors');
            $bag = [];
            if ($errors instanceof \Illuminate\Support\ViewErrorBag) {
                $bag = $errors->getBag('default')->all();
            } elseif ($errors instanceof \Illuminate\Support\MessageBag) {
                $bag = $errors->all();
            } elseif (is_array($errors)) {
                $bag = \Illuminate\Support\Arr::flatten($errors);
            }
            $this->assertEmpty($bag, "$label redirected with validation errors: ".implode(' | ', $bag));

            return;
        }

        $this->assertTrue(
            $status >= 200 && $status < 300,
            "$label returned HTTP $status"
        );
    }
}
