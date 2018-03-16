<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Service name that belongs the event messages produced.
    |--------------------------------------------------------------------------
    */
    'service' => 'service-name',

    /*
    |--------------------------------------------------------------------------
    | Middleware class where the event messages will be stored.
    |--------------------------------------------------------------------------
    */
    'middleware' => \Softonic\TransactionalEventPublisher\EventStoreMiddlewares\AmqpMiddleware::class,

    /*
    |--------------------------------------------------------------------------
    | Event Message class.
    |--------------------------------------------------------------------------
    */
    'message' =>  \Softonic\TransactionalEventPublisher\ValueObjects\EventMessage::class,

    /*
    |--------------------------------------------------------------------------
    | Message middleware. At this moment just an AMQP system.
    |--------------------------------------------------------------------------
    */
    'message_middleware' => 'amqp',

    /*
    |--------------------------------------------------------------------------
    | AMQP properties separated by key
    |--------------------------------------------------------------------------
    */
    'properties' => [
        'amqp' => [
            'host'                => 'localhost',
            'port'                => 5672,
            'username'            => 'rabbitmq-user',
            'password'            => 'rabbitmq-password',
            'vhost'               => 'rabbitmq-vhost',
            'exchange'            => 'rabbitmq-exchange',
            'exchange_type'       => 'topic',
            'exchange_durable'    => true,
            'consumer_tag'        => 'consumer',
            'ssl_options'         => [ ], // See https://secure.php.net/manual/en/context.ssl.php
            'connect_options'     => [ ], // See https://github.com/php-amqplib/php-amqplib/blob/master/PhpAmqpLib/Connection/AMQPSSLConnection.php
            'queue_properties'    => [ 'x-ha-policy' => [ 'S', 'all' ] ],
            'exchange_properties' => [ ],
            'timeout'             => 0,
        ],
    ],
];