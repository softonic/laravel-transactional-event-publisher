<?php

use Softonic\TransactionalEventPublisher\Builders\EventMessageBuilder;
use Softonic\TransactionalEventPublisher\Entities\EventMessage;
use Softonic\TransactionalEventPublisher\EventStoreMiddlewares\AmqpMiddleware;
use Softonic\TransactionalEventPublisher\EventStoreMiddlewares\DatabaseMiddleware;

return [
    /*
    |--------------------------------------------------------------------------
    | Service name that belongs the event messages produced.
    |--------------------------------------------------------------------------
    */
    'service' => env('SERVICE_NAME'),

    /*
    |--------------------------------------------------------------------------
    | Middleware class where the event messages will be stored.
    |--------------------------------------------------------------------------
    */
    'middleware' => DatabaseMiddleware::class,

    /*
    |--------------------------------------------------------------------------
    | Middleware that publishes the events.
    |--------------------------------------------------------------------------
    */
    'event_publisher_middleware' => AmqpMiddleware::class,

    /*
    |--------------------------------------------------------------------------
    | Event Message Builder class.
    |--------------------------------------------------------------------------
    */
    'messageBuilder' => EventMessageBuilder::class,

    /*
    |--------------------------------------------------------------------------
    | AMQP properties separated by key
    |--------------------------------------------------------------------------
    */
    'properties' => [
        'amqp' => [
            'host' => env('AMQP_HOST', 'localhost'),
            'port' => env('AMQP_PORT', 5672),
            'username' => env('AMQP_USER', 'guest'),
            'password' => env('AMQP_PASSWORD', 'guest'),
            'vhost' => env('AMQP_VHOST', 'domain-events'),
            'exchange' => env('AMQP_EXCHANGE', 'domain-events'),
            'exchange_type' => 'topic',
            'exchange_durable' => true,
            'consumer_tag' => 'consumer',
            'ssl_options' => [], // See https://secure.php.net/manual/en/context.ssl.php
            'connect_options' => [], // See https://github.com/php-amqplib/php-amqplib/blob/master/PhpAmqpLib/Connection/AMQPSSLConnection.php
            'queue_properties' => ['x-ha-policy' => ['S', 'all']],
            'exchange_properties' => [],
            'timeout' => 0,
            'routing_key_fields' => ['service', 'eventType', 'modelName'], // You can use any of the public attributes of your message, they are merged with '.'
        ],
    ],
];
