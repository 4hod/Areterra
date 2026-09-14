<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WorkspaceNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_landing_pages_are_available_without_changing_module_routes(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);

        foreach (['people', 'animal-care', 'operations', 'team', 'business', 'governance'] as $workspace) {
            $this->actingAs($admin)
                ->get("/{$workspace}")
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('Workspace')
                    ->where('workspace', $workspace));
        }
    }

    public function test_search_has_a_full_workspace_page(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);

        $this->actingAs($admin)
            ->get('/search-hub')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('SearchHub'));
    }
}

