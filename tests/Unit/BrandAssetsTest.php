<?php

namespace Tests\Unit;

use Tests\TestCase;

class BrandAssetsTest extends TestCase
{
    public function test_sidebar_keeps_the_full_colour_logo_visible_on_its_white_background(): void
    {
        $css = file_get_contents(resource_path('css/design-system.css'));

        $this->assertMatchesRegularExpression(
            '/\.portal-brand img\s*\{[^}]*filter:\s*none;/s',
            $css,
        );
    }
}
