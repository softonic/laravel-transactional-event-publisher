<?php

declare(strict_types=1);

namespace Softonic\TransactionalEventPublisher\Exceptions;

use LogicException;
use Softonic\TransactionalEventPublisher\Concerns\PublishesDomainEvents;

/**
 * The ModelObserver is attached to a model that does not use the PublishesDomainEvents trait, so its writes would not
 * run in the same transaction as the storage of their domain events.
 */
class ModelWithoutPublishesDomainEventsException extends LogicException
{
    public static function forModel(string $modelClass): self
    {
        return new self(
            'The model ' . $modelClass . ' is observed by the ModelObserver but does not use the '
            . PublishesDomainEvents::class . ' trait. Add the trait to the model (or to its base model).'
        );
    }
}
