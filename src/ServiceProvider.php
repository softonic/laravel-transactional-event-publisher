<?php

namespace Softonic\TransactionalEventPublisher;

use Bschmitt\Amqp\Amqp;
use Illuminate\Support\ServiceProvider as LaravelServiceProvider;
use Softonic\TransactionalEventPublisher\EventStoreMiddlewares\AmqpMiddleware;
use Softonic\TransactionalEventPublisher\Factories\AmqpMessageFactory;
use Softonic\TransactionalEventPublisher\Observers\ModelObserver;

/**
 * Class ServiceProvider
 *
 * @package Softonic\TransactionalEventPublisher
 */
class ServiceProvider extends LaravelServiceProvider
{
    /**
     * @var string
     */
    protected $packageName = 'transactional-event-publisher';

    /**
     * Bootstrap the application services.
     *
     */
    public function boot()
    {
        $this->publishes(
            [
                __DIR__ . '/../../config/' . $this->packageName . '.php' => config_path($this->packageName . '.php'),
            ],
            'config'
        );
    }

    /**
     * Register the application services.
     *
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/' . $this->packageName . '.php', $this->packageName);

        $this->app->bind(AmqpMiddleware::class, function(){
            return new AmqpMiddleware(new AmqpMessageFactory(), new Amqp(), config('transactional-event.publisher.properties.amqp'));
        });

        $this->app->bind(ModelObserver::class, function () {
            return new ModelObserver(
                resolve(config('transactional-event-publisher.middleware')),
                resolve(config('transactional-event-publisher.message'))
            );
        });
    }
}
