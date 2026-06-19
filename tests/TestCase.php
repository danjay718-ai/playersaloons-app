<?php

namespace Tests;

use App\Http\Middleware\UpdateUserOnlineStatus;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Disable the online-presence middleware globally in tests to prevent
        // Redis connection attempts. Tests that specifically test this middleware
        // should re-enable it via $this->withMiddleware(UpdateUserOnlineStatus::class).
        $this->withoutMiddleware(UpdateUserOnlineStatus::class);
    }
}
