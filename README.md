Laravel Transactional Event Publisher
=====================================

[![Latest Version](https://img.shields.io/github/release/softonic/laravel-transactional-event-publisher.svg?style=flat-square)](https://github.com/softonic/laravel-transactional-event-publisher/releases)
[![Software License](https://img.shields.io/badge/license-Apache%202.0-blue.svg?style=flat-square)](LICENSE.md)
[![Build Status](https://img.shields.io/travis/softonic/laravel-transactional-event-publisher/master.svg?style=flat-square)](https://travis-ci.org/softonic/glaravel-transactional-event-publisher)
[![Coverage Status](https://img.shields.io/scrutinizer/coverage/g/softonic/laravel-transactional-event-publisher.svg?style=flat-square)](https://scrutinizer-ci.com/g/softonic/laravel-transactional-event-publisher/code-structure)
[![Quality Score](https://img.shields.io/scrutinizer/g/softonic/laravel-transactional-event-publisher.svg?style=flat-square)](https://scrutinizer-ci.com/g/softonic/laravel-transactional-event-publisher)
[![Total Downloads](https://img.shields.io/packagist/dt/softonic/laravel-transactional-event-publisher.svg?style=flat-square)](https://packagist.org/packages/softonic/laravel-transactional-event-publisher)
[![Average time to resolve an issue](http://isitmaintained.com/badge/resolution/softonic/laravel-transactional-event-publisher.svg?style=flat-square)](http://isitmaintained.com/project/softonic/laravel-transactional-event-publisher "Average time to resolve an issue")
[![Percentage of issues still open](http://isitmaintained.com/badge/open/softonic/laravel-transactional-event-publisher.svg?style=flat-square)](http://isitmaintained.com/project/softonic/laravel-transactional-event-publisher "Percentage of issues still open")
Laravel package to handle atomicity between Eloquent model operations and domain event message generation. 

Main features
-------------

* Ensure every action has a domain event sent using an atomic transaction between Eloquent model operation, event generation and sent.
* Events sent to a AMQP system sync or async.
* Command to send all the events until now.

Installation
-------------

You can require the last version of the package using composer
```bash
composer require softonic/laravel-transactional-event-publisher
```

### Configuration

It is possible to configure the basic AMQP information, you can check it in `vendor/softonic/transactional-event-publisher/config/transactional-event-publisher.php` 

If you need further customization, you can publish the configuration.
```bash
php artisan vendor:publish --provider="Softonic\TransactionalEventPublisher\ServiceProvider" --tag=config
```

We provide `Softonic\TransactionalEventPublisher\EventStoreMiddlewares\DatabaseMiddleware`
and `Softonic\TransactionalEventPublisher\EventStoreMiddlewares\AmqpMiddleware` middlewares to store and send events.

#### Database middleware

This middleware just stores the events in a table in database. It can be useful if you want to expose the events as a REST endpoint or check your events history.

To configure this middleware you need to publish the migrations
```bash
php artisan vendor:publish --provider="Softonic\TransactionalEventPublisher\ServiceProvider" --tag=migrations
```
and execute them
```bash
php artisan migrate
```

#### Amqp middleware

This middleware publishes the events to an AMQP system. You just need to configure the AMQP connection using the configuration file or environmental variables.
As you can see, in the configuration you won't be able to define a queue. This is because the library just publishes the message to an exchange and is the events collector responsibility to declare the needed queues with the needed bindings.

### Publishing events in batches to improve performance

We provide a command to continuously publish events in batches.
You can find its signature in `Softonic\TransactionalEventPublisher\Console\Commands\EmitEvents`.
It will publish the events in batches of 100 by default if the option is not set.
You just need to create a job that will run indefinitely with the command `php artisan event-sourcing:emit`.

#### Sending all the events stored in database

Sometimes you will need to send all the events stored in the database. To do it, you can run the previous command with the option `--allEvents`.
You also have the option to use a [MySQL unbuffered connection](https://dev.mysql.com/doc/apis-php/en/apis-php-mysqlinfo.concepts.buffering.html) with the option `--unbufferedConnection` to retrieve a large amount of events without consuming all the memory.
Unbuffered connection example from `config/database.php`
```php
return [
    'connections' => [
        'mysql-unbuffered' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
            'options'   => [
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false // This option enabled the unbuffered queries.
            ],
        ],
    ]
];
```

### Registering Models

To choose what models should send domain events, you need to attach the `\Softonic\TransactionalEventPublisher\ModelObserver` observer class.

Example:

```
...

use App\Models\Post as MyModel;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Softonic\TransactionalEventPublisher\Observers\ModelObserver;

class EventServiceProvider extends ServiceProvider
{
    public function boot()
    {
        parent::boot();

        MyModel::observe(ModelObserver::class);
    }
    ...
}
```

### Custom middlewares

The middlewares should implement the `Softonic\TransactionalEventPublisher\Contracts\EventStoreMiddlewareContract` interface.
Its purpose is to store the domain event provided, so you can implement any storage for domain events.

### Custom messages

The `transactional-event.message` class must implement `EventMessageContract` and `transactional-event.middleware` class must implement `EventStoreMiddlewareContract`.

Considerations
==============

This package begins a database transaction in the following Eloquent Model events:

* creating
* updating
* deleting

And commit the database transaction when the event store middleware stores the event message successfully. On the other hand, if the event store couldn't store the event message would be a database rollback for the two operations (Eloquent model write + event message storing).
Take into account if an error occurs between the event of creating/updating/deleting and created/updated/deleted the transaction would remain started until the connection had been closed.

Testing
-------

`softonic/laravel-transactional-event-publisher` has a [PHPUnit](https://phpunit.de) test suite and a coding style compliance test suite using [PHP CS Fixer](http://cs.sensiolabs.org/).

To run the tests, run the following command from the project folder.

``` bash
$ make tests
```

To open a terminal in the dev environment:
``` bash
$ make debug
```

License
-------
The Apache 2.0 license. Please see [LICENSE](LICENSE) for more information.

[PSR-2]: http://www.php-fig.org/psr/psr-2/
[PSR-4]: http://www.php-fig.org/psr/psr-4/
