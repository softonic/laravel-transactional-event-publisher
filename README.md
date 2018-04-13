Laravel Transactional Event Publisher
=====================================

[![Latest Version](https://img.shields.io/github/release/softonic/laravel-transactional-event-publisher.svg?style=flat-square)](https://github.com/softonic/laravel-transactional-event-publisher/releases)
[![Software License](https://img.shields.io/badge/license-Apache%202.0-blue.svg?style=flat-square)](LICENSE.md)
[![Build Status](https://img.shields.io/travis/softonic/laravel-transactional-event-publisher/master.svg?style=flat-square)](https://travis-ci.org/softonic/glaravel-transactional-event-publisher)
[![Coverage Status](https://img.shields.io/scrutinizer/coverage/g/softonic/laravel-transactional-event-publisher.svg?style=flat-square)](https://scrutinizer-ci.com/g/softonic/laravel-transactional-event-publisher/code-structure)
[![Quality Score](https://img.shields.io/scrutinizer/g/softonic/laravel-transactional-event-publisher.svg?style=flat-square)](https://scrutinizer-ci.com/g/softonic/laravel-transactional-event-publisher)
[![Total Downloads](https://img.shields.io/packagist/dt/softonic/laravel-transactional-event-publisher.svg?style=flat-square)](https://packagist.org/packages/softonic/laravel-transactional-event-publisher)

Laravel package to handle atomicity between Eloquent model operations and domain event message generation. 

Main features
-------------

* Ensure every action has a domain event sent using an atomic transaction between Eloquent model operation, event generation and sent.
* Events sent to a AMQP system sync or async

Installation
-------------

You can require the last version of the package using composer
```bash
composer require softonic/laravel-transactional-event-publisher
```

### Configuration

It is possible to configure the basic AMQP information, you can check it in `vendor/softonic/transactional-event-publisher.php/config/transactional-event-publisher.php` 

If you need further customization, you can publish the configuration.
```bash
php artisan vendor:publish --provider="Softonic\TransactionalEventPublisher\ServiceProvider" --tag=config
```

We provide `Softonic\TransactionalEventPublisher\EventStoreMiddlewares\AmqpMiddleware`, 
 `Softonic\TransactionalEventPublisher\EventStoreMiddlewares\DatabaseMiddleware` 
 and `Softonic\TransactionalEventPublisher\EventStoreMiddlewares\AsyncAmqpMiddleware` middlewares to send events two AMQP.

#### Sync AMQP middleware 

To use the sync AMQP you jsut need to configure the AMQP connection using the configuration file or environmental variables.
 
#### Async AMQP middleware

You need to do the Sync AMQP middleware steps and continue with these:

* Create the job table if you don't have it in the project
```bash
php artisan queue:table
php artisan migrate
```
* Run a worker to actually send the events
```bash
php artisan queue:work --queue=domainEvents
```

The job table is needed because to ensure that a job is dispatched after an action, we need to do a transaction, so the job must use the database driver.

#### Database middleware

This middleware just store the events in a table in database. It can be useful if you want to expose the events as a REST endpoint or check your events history.

To configure this middleware you need to publish the migrations
```bash
php artisan vendor:publish --provider="Softonic\TransactionalEventPublisher\ServiceProvider" --tag=migrations
```
and execute the migrations
```bash
php artisan migrate
```

### Registering Models

To choose what models should send domain events, you need to attach the `\Softonic\TransactionalEventPublisher\ModelObserver` observer class.

Example:

```
...
class AppServiceProvider extends ServiceProvider
{
    public function boot()
    {
        ModelToSendEvents::observe(\Softonic\TransactionalEventPublisher\ModelObserver::class);
    }
    ...
}
```

### Custom middlewares

The middlewares should implement the `Softonic\TransactionalEventPublisher\Contracts\EventStoreMiddlewareContract` interface.
Its purpose is store the domain event provided, so you can implement any storage for domain events.

### Custom messages

The `transactional-event.message` class must implements `EventMessageContract` and `transactional-event.middleware` class must implements `EventStoreMiddlewareContract`

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

To run the tests and php-cs-fixer, run the following command from the project folder.

``` bash
$ docker-compose run test
```

To run interactively using [PsySH](http://psysh.org/):
``` bash
$ docker-compose run psysh
```

To run phpunit tests, run the following command from the project folder:

```bash
$ docker-compose run phpunit
```

License
-------

The Apache 2.0 license. Please see [LICENSE](LICENSE) for more information.

[PSR-2]: http://www.php-fig.org/psr/psr-2/
[PSR-4]: http://www.php-fig.org/psr/psr-4/
