<?php

namespace Softonic\TransactionalEventPublisher\Builders;

use Softonic\TransactionalEventPublisher\Contracts\MessageBuilderContract;
use Softonic\TransactionalEventPublisher\Entities\EventMessage;

/**
 * Class EventBusMessageBuilder
 *
 * @package Softonic\TransactionalEventPublisher\Builders
 */
class EventBusMessageBuilder implements MessageBuilderContract
{
    private $eventMessage;

    public function __construct(EventMessage $eventMessage)
    {
        $this->eventMessage = $eventMessage;
    }

    /**
     * {@inheritdoc}
     */
    public function build($eventType, $modelName, $payload, array $metas = [])
    {
        $this->eventMessage->service = config('transactional-event-publisher.service');
        $this->eventMessage->eventType = $eventType;
        $this->eventMessage->modelName = $modelName;
        $this->eventMessage->eventName = $this->buildEventName($modelName, $eventType);
        $this->eventMessage->payload = $payload;
        $this->eventMessage->createdAt = date('Y-m-d H:i:s');
        $this->eventMessage->meta = $this->buildMetas($metas);

        return $this->eventMessage;
    }

    /**
     * {@inheritdoc}
     */
    public function buildMetas(array $metas)
    {
        return $metas;
    }

    protected function buildEventName($modelName, $event)
    {
        return $modelName . ucfirst($event);
    }
}
