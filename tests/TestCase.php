<?php

namespace Softonic\TransactionalEventPublisher;

use Mockery;
use Orchestra\Testbench\TestCase as TestbenchTestCase;
use Override;

class TestCase extends TestbenchTestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/../database/factories');
    }

    #[Override]
    protected function tearDown(): void
    {
        if (class_exists('Mockery')) {
            if ($container = Mockery::getContainer()) {
                $this->addToAssertionCount($container->mockery_getExpectationCount());
            }

            Mockery::close();
        }

        parent::tearDown();
    }
}
