<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_administrator_can_search_members_without_a_server_error(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $member = Member::create([
            'first_name' => 'Amy',
            'last_name' => 'Buckle',
            'preferred_name' => null,
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->getJson('/search?q=Amy')
            ->assertOk()
            ->assertJsonPath('results.Members.0.title', 'Amy Buckle')
            ->assertJsonPath('results.Members.0.url', "/members/{$member->id}");
    }

    public function test_member_search_matches_a_preferred_name(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        Member::create([
            'first_name' => 'Alexandra',
            'last_name' => 'Example',
            'preferred_name' => 'Lexi',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->getJson('/search?q=Lexi')
            ->assertOk()
            ->assertJsonPath('results.Members.0.title', 'Lexi Example');
    }

    public function test_search_results_without_detail_pages_link_to_their_module(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        Task::create([
            'title' => 'Repair the garden gate',
            'priority' => 'medium',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->getJson('/search?q=garden')
            ->assertOk()
            ->assertJsonPath('results.Tasks.0.url', '/tasks');
    }
}
