<?php

namespace Softonic\TransactionalEventPublisher\Contracts;

/**
 * Interface MessageBuilderContract
 *
 * @package Softonic\TransactionalEventPublisher\Contracts
 */
interface MessageBuilderContract
{
    /**
     * Builds the message to send to the event bus.
     *
     * @param       $eventType
     * @param       $modelName
     * @param       $payload
     * @param array $metas
     *
     * @return mixed
     */
    public function build($eventType, $modelName, $payload, array $metas = []);

    /**
     * Builds the custom metas.
     *
     * @param array $metas
     *
     * @return mixed
     */
    public function buildMetas(array $metas);
}
