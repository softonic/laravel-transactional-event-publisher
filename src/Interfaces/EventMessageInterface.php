<?php

declare(strict_types=1);

namespace Softonic\TransactionalEventPublisher\Interfaces;

use JsonSerializable;

/**
 * @property-read string $service
 * @property-read string $eventType
 * @property-read string $modelName
 */
interface EventMessageInterface extends JsonSerializable
{
    /**
     * Returns the message in an array format.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
