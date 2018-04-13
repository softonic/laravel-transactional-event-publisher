<?php

namespace Softonic\TransactionalEventPublisher;

use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function tearDown()
    {
        if (class_exists('Mockery')) {
            if ($container = \Mockery::getContainer()) {
                $this->addToAssertionCount($container->mockery_getExpectationCount());
            }

            \Mockery::close();
        }
    }
}
