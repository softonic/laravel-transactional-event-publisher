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

Add the `\Softonic\TransactionalEventPublisher\Concerns\PublishesDomainEvents` trait to the models that publish domain
events. Nothing else has to be registered: the trait stores the `created`, `updated` and `deleted` event of each insert,
update (including `increment()`/`decrement()`) and delete of the model. Models without the trait publish nothing.

Each write runs in its own transaction, together with the storage of its event: if anything fails (the query, the
message builder, the event store or a listener halting the operation), only that transaction level is rolled back, so the
caller's transaction is never left open.

Using the trait in a base model is enough to cover all the models extending it, each one publishing with its own name.

```php
use Illuminate\Database\Eloquent\Model;
use Softonic\TransactionalEventPublisher\Concerns\PublishesDomainEvents;

abstract class BaseModel extends Model
{
    use PublishesDomainEvents;
}
```

The event is built and stored by `\Softonic\TransactionalEventPublisher\Services\EventStore`, resolved from the container
on every write, so a project can replace it with its own binding.

The listeners of the trait are registered when the model boots (on its first instantiation), so they run before the
listeners and observers registered later for the same events. The model must boot once the event dispatcher is set,
as any Eloquent listener: do not instantiate it in the `register()` of a service provider. Writes made without model
events (`saveQuietly()`, `Model::withoutEvents()`) store no event.

If the model overrides `performInsert()`, `performUpdate()`, `incrementOrDecrement()` or `delete()`, the method of the
model takes precedence over the trait one. Import the trait method with an alias and call it from the override, so the write still runs in its
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

#### Upgrading to 12.0

The trait is now the only piece needed to publish: `\Softonic\TransactionalEventPublisher\Observers\ModelObserver` is
removed.

- Remove every `observe(ModelObserver::class)` call and `#[ObservedBy(ModelObserver::class)]` attribute. Any one left
  fails because the class no longer exists.
- Add the trait to every model that was observed, or to its base model. A model that was observed without the trait now
  publishes nothing.
- Keep the trait only in the models that publish: a model that used the trait without being observed now publishes.
- If the project rebound `ModelObserver` in the container, rebind `EventStore` instead.
- The configuration, the messages, the middlewares and the `EmitEvents` command do not change.

#### Disabling the publication of events

`\Softonic\TransactionalEventPublisher\DomainEvents` disables the storage of domain events for the whole process, for
example during a bulk synchronisation. It is checked each time an event is going to be stored, so it works regardless of
when the models are booted. While it is disabled no event is stored, and the writes of the models still run in their own
transaction.

```php
use Softonic\TransactionalEventPublisher\DomainEvents;

DomainEvents::disable();
// ...
DomainEvents::enable();

// Or only for a callback, restoring the previous state afterwards (even if it throws):
DomainEvents::withoutPublishing(fn () => $this->synchronise());
```

The switch is static, so in long-running processes (queue workers, Octane) make sure it is enabled again when the work
that disabled it finishes.

### Custom middlewares

The middlewares should implement the `Softonic\TransactionalEventPublisher\Interfaces\EventStoreMiddlewareInterface` interface.
Its purpose is to store the domain event provided, so you can implement any storage for domain events.

### Custom messages

The `transactional-event.messageBuilder` class must implement `EventMessageBuilderInterface` and `transactional-event.middleware` class must implement `EventStoreMiddlewareInterface`.

The builder should return a `EventMessageInterface` value object. It just needs to implement the `toArray` and `jsonSerialize` methods with all the attributes that you need.

Considerations
==============

- Each write of a model using the trait runs in its own transaction level together with the storage of its event. If
  anything fails, only that level is rolled back and the exception reaches the caller, so no transaction is ever left
  open.
- If the write runs inside a transaction of the caller, the event is only final when that outer transaction commits.
  With `DatabaseMiddleware` the stored event is rolled back with it. With `AmqpMiddleware` the message is already
  published to the broker, so an outer rollback does not undo it. Use `DatabaseMiddleware` and the `EmitEvents` command
  when the event must never be published without its write.
- Only Eloquent model writes publish events. Query builder writes (`Model::query()->update()`, `insert()`, `upsert()`,
  mass `delete()`...) and writes without model events (`saveQuietly()`, `Model::withoutEvents()`) store nothing.

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
