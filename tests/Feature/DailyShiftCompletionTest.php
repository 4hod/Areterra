<?php

namespace Tests\Feature;

use App\Models\Animal;
use App\Models\DailyShiftCompletion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DailyShiftCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_finish_today_when_all_required_work_is_complete(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)
            ->post('/today/finish')
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('daily_shift_completions', [
            'user_id' => $staff->id,
            'work_date' => today()->toDateString(),
        ]);
    }

    public function test_staff_cannot_finish_today_while_required_work_is_outstanding(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        Animal::create(['name' => 'Demon', 'species' => 'Macaw']);

        $this->actingAs($staff)
            ->post('/today/finish')
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('daily_shift_completions', 0);
    }

    public function test_today_page_shows_the_current_users_finish_time(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        DailyShiftCompletion::create([
            'user_id' => $staff->id,
            'work_date' => today(),
            'completed_at' => now(),
        ]);

        $this->actingAs($staff)
            ->get('/today')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Today')
                ->where('shiftCompletedAt', fn ($value) => filled($value)));
    }
}
