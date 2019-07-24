<?php

use Softonic\TransactionalEventPublisher\Model\DomainEvent;
use Softonic\TransactionalEventPublisher\CustomEventMessage;
use Softonic\TransactionalEventPublisher\TestModel;

$factory->define(DomainEvent::class, function () {
    return ['message' => new CustomEventMessage(new TestModel(), 'testEvent')];
});
