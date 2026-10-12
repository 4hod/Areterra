<?php

namespace Tests\Feature;

use App\Models\Animal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnimalWelfareTest extends TestCase
{
    use RefreshDatabase;

    public function test_monitoring_upserts_on_animal_and_date(): void
    {
        $user = User::factory()->create(['role' => 'staff']);
        $animal = Animal::create(['name' => 'Lily', 'species' => 'Chinchilla']);

        $this->actingAs($user)->post("/animals/{$animal->id}/monitoring", [
            'monitor_date' => today()->toDateString(),
            'weight_grams' => 500,
        ])->assertRedirect();

        $this->actingAs($user)->post("/animals/{$animal->id}/monitoring", [
            'monitor_date' => today()->toDateString(),
            'weight_grams' => 520,
            'appetite' => 'good',
        ])->assertRedirect();

        $this->assertSame(1, $animal->dailyMonitoring()->count());
        $this->assertSame(520, $animal->dailyMonitoring()->first()->weight_grams);
    }

    public function test_species_group_check_logs_everyone_and_flags_one(): void
    {
        $user = User::factory()->create(['role' => 'staff']);
        $demon = Animal::create(['name' => 'Demon', 'species' => 'Macaw']);
        $angel = Animal::create(['name' => 'Angel', 'species' => 'Macaw']);
        $other = Animal::create(['name' => 'Lily', 'species' => 'Chinchilla']);

        $this->actingAs($user)->post('/welfare-checks/species', [
            'species' => 'Macaw',
            'verification_confirmed' => true,
            // The controller and the Animals page both use 'checks'. This test
            // posted 'flagged', so the override never applied and the flagged
            // animal silently came back green.
            'checks' => [
                ['animal_id' => $demon->id, 'status' => null, 'fed' => true, 'treats_given' => false],
                ['animal_id' => $angel->id, 'status' => 'amber', 'notes' => 'Feather plucking', 'fed' => true, 'treats_given' => false],
            ],
        ])->assertRedirect();

        $this->assertSame(1, $demon->welfareChecks()->count());
        $this->assertSame(1, $angel->welfareChecks()->count());
        $this->assertSame(0, $other->welfareChecks()->count());

        $this->assertSame('green', $demon->fresh()->welfare_status);
        $this->assertSame('amber', $angel->fresh()->welfare_status);
        $this->assertTrue($angel->welfareChecks()->first()->concern);
    }

    public function test_individual_check_updates_animal_welfare_status(): void
    {
        $user = User::factory()->create(['role' => 'staff']);
        $animal = Animal::create(['name' => 'Fidget', 'species' => 'Chinchilla']);

        $this->actingAs($user)->post("/animals/{$animal->id}/welfare-checks", [
            'status' => 'red',
            'notes' => 'Not eating',
            'concern' => true,
            'fed' => false,
            'treats_given' => false,
        ])->assertRedirect();

        $this->assertSame('red', $animal->fresh()->welfare_status);
    }

    public function test_animal_care_board_summarises_todays_work_and_upcoming_vet_care(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $checked = Animal::create(['name' => 'Rico', 'species' => 'Macaw']);
        $outstanding = Animal::create(['name' => 'Lily', 'species' => 'Chinchilla', 'welfare_status' => 'amber']);

        $checked->welfareChecks()->create([
            'user_id' => $manager->id,
            'status' => 'green',
            'fed' => true,
            'concern' => false,
        ]);
        $checked->dailyMonitoring()->create([
            'user_id' => $manager->id,
            'monitor_date' => today(),
            'concern' => false,
        ]);
        $outstanding->vetRecords()->create([
            'visit_date' => today()->subMonth(),
            'next_due_date' => today()->addWeek(),
            'reason' => 'Follow-up',
        ]);

        $props = $this->actingAs($manager)->get('/animals')->assertOk()->viewData('page')['props'];

        $this->assertSame([
            'total' => 2,
            'checked' => 1,
            'monitored' => 1,
            'concerns' => 1,
            'vet_due' => 1,
        ], $props['summary']);
        $this->assertTrue($props['bySpecies']['Macaw'][0]['checked_today']);
        $this->assertFalse($props['bySpecies']['Chinchilla'][0]['monitored_today']);
        $this->assertSame(today()->addWeek()->toDateString(), $props['bySpecies']['Chinchilla'][0]['next_vet_due']);
    }
}
