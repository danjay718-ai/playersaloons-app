<?php

namespace Tests\Unit;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic unit test example.
     */
    public function test_application_is_configured(): void
    {
        $this->assertNotEmpty(config('app.name'));
    }
}
