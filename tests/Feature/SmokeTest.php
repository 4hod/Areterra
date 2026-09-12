<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Loads every page in the Hub as an administrator and checks none of them
 * crash. Not a substitute for behavioural tests — but before a rollout, a page
 * that 500s the moment somebody clicks it is the failure that matters most,
 * and this catches all of them in one run.
 *
 * Deliberately run against an empty database: that is what the Hub looks like
 * on day one, and "works with data, explodes when empty" is a classic
 * first-week bug.
 */
class SmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_page_loads_without_a_server_error(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $broken = [];
        $checked = 0;

        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            $uri = $route->uri();

            // Parameterised routes need fixtures and are covered by their own
            // tests; internal and asset routes aren't pages.
            if (str_contains($uri, '{') || str_starts_with($uri, '_') || str_starts_with($uri, 'storage')) {
                continue;
            }

            $status = $this->actingAs($admin)->get('/'.ltrim($uri, '/'))->getStatusCode();
            $checked++;

            if ($status >= 500) {
                $broken[] = "/{$uri} → {$status}";
            }
        }

        $this->assertGreaterThan(40, $checked, 'Expected to smoke-test most of the app.');
        $this->assertSame([], $broken, "Pages returning a server error:\n".implode("\n", $broken));
    }

    public function test_a_volunteer_is_not_shown_finance_or_safeguarding(): void
    {
        $volunteer = User::factory()->create(['role' => 'volunteer']);

        foreach (['/finance', '/safeguarding', '/payroll', '/settings'] as $uri) {
            $status = $this->actingAs($volunteer)->get($uri)->getStatusCode();

            $this->assertContains(
                $status,
                [403, 404],
                "A volunteer could reach {$uri} (status {$status}).",
            );
        }
    }
}
