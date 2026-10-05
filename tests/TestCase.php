<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (config('database.default') !== 'pgsql' || config('database.connections.pgsql.database') !== 'tli_store_test') {
            throw new \RuntimeException('Feature tests require the dedicated PostgreSQL database tli_store_test.');
        }
        $this->withoutVite();
    }
}
