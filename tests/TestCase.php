<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Feature tests assert Inertia responses and do not execute the compiled
        // frontend. Keeping Vite disabled here makes the PHP suite independent
        // from the separate frontend build job in CI.
        $this->withoutVite();
    }
}
