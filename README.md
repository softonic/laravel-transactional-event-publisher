Laravel Transactional Event Publisher
=====================================

[![Latest Version](https://img.shields.io/github/release/softonic/laravel-transactional-event-publisher.svg?style=flat-square)](https://github.com/softonic/laravel-transactional-event-publisher/releases)
[![Software License](https://img.shields.io/badge/license-Apache%202.0-blue.svg?style=flat-square)](LICENSE.md)
[![Build Status](https://img.shields.io/github/actions/workflow/status/softonic/laravel-transactional-event-publisher/tests.yml?branch=master&style=flat-square)](https://github.com/softonic/laravel-transactional-event-publisher/actions)
[![Coverage Status](https://img.shields.io/scrutinizer/coverage/g/softonic/laravel-transactional-event-publisher.svg?style=flat-square)](https://scrutinizer-ci.com/g/softonic/laravel-transactional-event-publisher/code-structure)
[![Quality Score](https://img.shields.io/scrutinizer/g/softonic/laravel-transactional-event-publisher.svg?style=flat-square)](https://scrutinizer-ci.com/g/softonic/laravel-transactional-event-publisher)
[![Total Downloads](https://img.shields.io/packagist/dt/softonic/laravel-transactional-event-publisher.svg?style=flat-square)](https://packagist.org/packages/softonic/laravel-transactional-event-publisher)
[![Average time to resolve an issue](http://isitmaintained.com/badge/resolution/softonic/laravel-transactional-event-publisher.svg?style=flat-square)](http://isitmaintained.com/project/softonic/laravel-transactional-event-publisher "Average time to resolve an issue")
[![Percentage of issues still open](http://isitmaintained.com/badge/open/softonic/laravel-transactional-event-publisher.svg?style=flat-square)](http://isitmaintained.com/project/softonic/laravel-transactional-event-publisher "Percentage of issues still open")

Laravel package to handle atomicity between Eloquent model operations and domain event message generation.

**Requirements:**
- PHP >= 8.5
- Laravel 12.x

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
It will publish the events in batches of 100 by default, or you can change it with the option `--batchSize`.
You just need to create a job that will run indefinitely with the command `php artisan event-sourcing:emit`.

#### Sending all the events stored in database

By default, the command `php artisan event-sourcing:emit` will send all the events stored in database using a [MySQL unbuffered connection](https://dev.mysql.com/doc/apis-php/en/apis-php-mysqlinfo.concepts.buffering.html).
Otherwise, a Mysql buffered Connection it is used to delete the events from database after they have been sent.

You can specify the connection to use with the option `--dbConnection` for the buffered connection and `--dbConnectionUnbuffered` for the unbuffered connection.
Unbuffered connection example from `config/database.php`
```php
return [
    'connections' => [
        'mysql-unbuffered' => [
            'driver'      => 'mysql',
            'host'        => env('DB_HOST', '127.0.0.1'),
            'port'        => env('DB_PORT', '3306'),
            'database'    => env('DB_DATABASE', 'forge'),
            'username'    => env('DB_USERNAME', 'forge'),
            'password'    => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset'     => 'utf8',
            'collation'   => 'utf8_unicode_ci',
            'prefix'      => '',
            'strict'      => true,
            'engine'      => null,
            'options'     => [
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false,
            ],
        ],
    ]
];
```

### Registering Models

To choose what models should send domain events, use the `\Softonic\TransactionalEventPublisher\Concerns\PublishesDomainEvents` trait.
It registers the observer by itself, and runs each insert, update and delete together with the storage of its domain event in
the same transaction. If anything fails (the query, the event store or a listener halting the operation), only that
transaction level is rolled back, so the caller's transaction is never left open.

Using it in a base model is enough to cover all the models extending it.

Example:

```php
use Illuminate\Database\Eloquent\Model;
use Softonic\TransactionalEventPublisher\Concerns\PublishesDomainEvents;

abstract class BaseModel extends Model
{
    use PublishesDomainEvents;
}
```

#### Migrating from `ModelObserver`

Attaching the `\Softonic\TransactionalEventPublisher\Observers\ModelObserver` observer (`MyModel::observe(ModelObserver::class)`)
is deprecated. It keeps working as before, but it leaves a transaction level open when the query fails, and it triggers an
`E_USER_DEPRECATED` once per process. To migrate:

1. Add the `PublishesDomainEvents` trait to the models (or to their base model).
2. Remove the `observe(ModelObserver::class)` calls. While both are present, the `ModelObserver` does nothing for models
   using the trait, so no event is duplicated.

If the model overrides `performInsert()`, `performUpdate()` or `delete()`, the method of the model takes precedence over
the trait one. Import the trait method with an alias and call it from the override, so the write still runs in its
transaction:

```php
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Softonic\TransactionalEventPublisher\Concerns\PublishesDomainEvents;

abstract class BaseModel extends Model
{
    use PublishesDomainEvents {
        performInsert as transactionalPerformInsert;
    }

    protected function performInsert(Builder $query)
    {
        try {
            return $this->transactionalPerformInsert($query);
        } catch (UniqueConstraintViolationException $exception) {
            // The transaction level of the insert is already rolled back here.
            ...
        }
    }
}
```

### Custom middlewares

The middlewares should implement the `Softonic\TransactionalEventPublisher\Interfaces\EventStoreMiddlewareInterface` interface.
Its purpose is to store the domain event provided, so you can implement any storage for domain events.

### Custom messages

The `transactional-event.messageBuilder` class must implement `EventMessageBuilderInterface` and `transactional-event.middleware` class must implement `EventStoreMiddlewareInterface`.

The builder should return a `EventMessageInterface` value object. It just needs to implement the `toArray` and `jsonSerialize` methods with all the attributes that you need.

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

`softonic/laravel-transactional-event-publisher` has a [PHPUnit](https://phpunit.de) test suite and a coding style compliance test suite using [PHP CS Fixer](http://cs.sensiolabs.org/) following [PSR-12](http://www.php-fig.org/psr/psr-12/).

To run the tests, run the following command from the project folder:

``` bash
$ docker compose run --rm tests
```

To run PHPUnit only:

``` bash
$ docker compose run --rm phpunit
```

To run PHPStan static analysis:

``` bash
$ docker compose run --rm phpstan
```

To check code style:

``` bash
$ docker compose run --rm php composer run checkstyle
```

To fix code style issues:

``` bash
$ docker compose run --rm fixcs
```

To open a terminal in the dev environment:

``` bash
$ docker compose run --rm --entrypoint=bash php
```

For debugging with Xdebug:

``` bash
$ docker compose run --rm debug
```

License
-------
The Apache 2.0 license. Please see [LICENSE](LICENSE) for more information.

[PSR-12]: http://www.php-fig.org/psr/psr-12/
[PSR-4]: http://www.php-fig.org/psr/psr-4/
