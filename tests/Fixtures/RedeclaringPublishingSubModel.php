<?php

declare(strict_types=1);

namespace Softonic\TransactionalEventPublisher\Fixtures;

use Softonic\TransactionalEventPublisher\Concerns\PublishesDomainEvents;

/**
 * Declares the trait again although its base model already uses it.
 */
class RedeclaringPublishingSubModel extends AbstractPublishingModel
{
    use PublishesDomainEvents;
}
