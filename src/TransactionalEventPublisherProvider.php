<?php

namespace Softonic\TransactionalEventPublisher;

use Bschmitt\Amqp\Amqp;
use Illuminate\Support\ServiceProvider;
use Softonic\TransactionalEventPublisher\Builders\EventBusMessageBuilder;
use Softonic\TransactionalEventPublisher\Entities\EventMessage;
use Softonic\TransactionalEventPublisher\EventStoreMiddlewares\AmqpMiddleware;
use Softonic\TransactionalEventPublisher\Factories\AmqpMessageFactory;
use Softonic\TransactionalEventPublisher\Observers\ModelObserver;

/**
 * Class TransactionalEventPublisherProvider
 *
 * @package Softonic\TransactionalEventPublisher
 */
class TransactionalEventPublisherProvider extends ServiceProvider
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

        $this->app->bind(ModelObserver::class, function () {
            return new ModelObserver(
                new EventBusMessageBuilder(new EventMessage()),
                new AmqpMiddleware(new AmqpMessageFactory(), new Amqp(), config('transactional-event.publisher.properties.amqp'))
            );
        });
    }
}
