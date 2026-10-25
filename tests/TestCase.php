<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Vite;

abstract class TestCase extends BaseTestCase
{
    /**
     * Tests always see production assets, even while a local Vite dev server
     * is running, so CSP assertions do not depend on the developer's machine.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Vite::useHotFile(storage_path('framework/testing/vite.hot'));
    }
}
