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
            // The controller and the Animals page both use 'checks'. This test
            // posted 'flagged', so the override never applied and the flagged
            // animal silently came back green.
            'checks' => [
                ['animal_id' => $angel->id, 'status' => 'amber', 'notes' => 'Feather plucking'],
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
        ])->assertRedirect();

        $this->assertSame('red', $animal->fresh()->welfare_status);
    }
}
