<?php

namespace Tests\Unit;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class FrontendDialogsTest extends TestCase
{
    public function test_frontend_does_not_use_native_browser_prompts_or_confirms(): void
    {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(resource_path('js'), RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if (! in_array($file->getExtension(), ['ts', 'tsx'], true)) {
                continue;
            }

            $source = file_get_contents($file->getPathname());

            $this->assertDoesNotMatchRegularExpression(
                '/\bwindow\.(?:prompt|confirm|alert)\s*\(/',
                $source,
                "Native browser dialog found in {$file->getPathname()}",
            );
        }
    }
}
