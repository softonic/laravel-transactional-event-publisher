Laravel Transactional Event Publisher
=====================================

[![Latest Version](https://img.shields.io/github/release/softonic/laravel-transactional-event-publisher.svg?style=flat-square)](https://github.com/softonic/laravel-transactional-event-publisher/releases)
[![Software License](https://img.shields.io/badge/license-Apache%202.0-blue.svg?style=flat-square)](LICENSE.md)
[![Build Status](https://img.shields.io/travis/softonic/laravel-transactional-event-publisher/master.svg?style=flat-square)](https://travis-ci.org/softonic/glaravel-transactional-event-publisher)
[![Coverage Status](https://img.shields.io/scrutinizer/coverage/g/softonic/laravel-transactional-event-publisher.svg?style=flat-square)](https://scrutinizer-ci.com/g/softonic/laravel-transactional-event-publisher/code-structure)
[![Quality Score](https://img.shields.io/scrutinizer/g/softonic/laravel-transactional-event-publisher.svg?style=flat-square)](https://scrutinizer-ci.com/g/softonic/laravel-transactional-event-publisher)
[![Total Downloads](https://img.shields.io/packagist/dt/softonic/laravel-transactional-event-publisher.svg?style=flat-square)](https://packagist.org/packages/softonic/laravel-transactional-event-publisher)

Laravel package to handle atomicity between Eloquent model operations and event message generation and storing linked to this model operation. Main features:

* Atomic transaction between Eloquent model operation, event generation and sent.
* Events sent to a AMQP system
* Chance of adding a custom Event message builder

Documentation
-------------

To add the Observer generates and sends to the event Store just add it in the `boot()` method of `AppServiceProvider` class

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

If you want you can pass your custom MessageBuilder and EventStoreMiddleware if you want. You can pass it your custom classes in the `ModelObserver` provider

```
...
class ModelObserverProvider() extends ServiceProvider
{
    public function register()
    {
        $this->app->bind(ModelObserver::class, function(){
            return new ModelObserver(new CustomMessageBuilder(), new CustomEventStoreMiddleware())
        });
    }
}
```

The `CustomMessageBuilder` must implements `MessageBuilderContract` and `CustomEventStoreMiddleware` must implements `EventStoreMiddlewareContract`


Testing
-------

`softonic/laravel-transactional-event-publisher` has a [PHPUnit](https://phpunit.de) test suite and a coding style compliance test suite using [PHP CS Fixer](http://cs.sensiolabs.org/).

To run the tests, run the following command from the project folder.

``` bash
$ docker-compose run test
```

To run interactively using [PsySH](http://psysh.org/):
``` bash
$ docker-compose run psysh
```

License
-------

The Apache 2.0 license. Please see [LICENSE](LICENSE) for more information.

[PSR-2]: http://www.php-fig.org/psr/psr-2/
[PSR-4]: http://www.php-fig.org/psr/psr-4/
