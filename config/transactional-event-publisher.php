<?php

return [
    'service' => 'service-name',
    'middleware' => \Softonic\TransactionalEventPublisher\EventStoreMiddlewares\AmqpMiddleware::class,
    'message' =>  \Softonic\TransactionalEventPublisher\ValueObjects\EventMessage::class,
    'message_middleware' => 'amqp',
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