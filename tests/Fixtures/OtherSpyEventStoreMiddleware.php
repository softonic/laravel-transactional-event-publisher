<?php

declare(strict_types=1);

namespace Softonic\TransactionalEventPublisher\Fixtures;

/**
 * A second spy, bound in the container under its own class name, to configure several middlewares.
 */
class OtherSpyEventStoreMiddleware extends SpyEventStoreMiddleware
{
}
