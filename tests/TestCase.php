<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Contracts\Console\Kernel;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Avoid database-backed sessions interfering with transactions
        config(['session.driver' => 'array']);
        // Ensure base seeders run for templates/plans
        $this->seed();
    }

    // DatabaseMigrations will handle refreshing schema between tests without nested transactions
}
