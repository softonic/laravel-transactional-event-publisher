<?php

namespace Softonic\TransactionalEventPublisher;

use Illuminate\Support\ServiceProvider as LaravelServiceProvider;
use Softonic\Amqp\Amqp;
use Softonic\TransactionalEventPublisher\Console\Commands\EmitEvents;
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
    protected string $packageName = 'transactional-event-publisher';

    /**
     * Bootstrap the application services.
     *
     */
    public function boot()
    {
        $this->publishes(
            [
                __DIR__ . '/../config/' . $this->packageName . '.php' => config_path($this->packageName . '.php'),
            ],
            'config'
        );

        $this->publishes(
            [
                __DIR__ . '/../database/migrations/' => database_path('migrations'),
            ],
            'migrations'
        );
    }

    /**
     * Register the application services.
     *
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/' . $this->packageName . '.php', $this->packageName);

        $this->app->bind(AmqpMiddleware::class, function () {
            return new AmqpMiddleware(
                new AmqpMessageFactory(),
                new Amqp(),
                config('transactional-event-publisher.properties.amqp')
            );
        });

        $this->app->bind(ModelObserver::class, function () {
            $middlewareClasses = config('transactional-event-publisher.middleware');
            if (!is_array($middlewareClasses)) {
                $middlewareClasses = [$middlewareClasses];
            }

            $middlewares = [];
            foreach ($middlewareClasses as $middlewareClass) {
                $middlewares[] = resolve($middlewareClass);
            }

            return new ModelObserver(
                $middlewares,
                new (config('transactional-event-publisher.messageBuilder'))
            );
        });

        $this->app->bindMethod(
            'Softonic\TransactionalEventPublisher\Console\Commands\EmitEvents@handle',
            function ($job) {
                return $job->handle(
                    resolve(config('transactional-event-publisher.event_publisher_middleware')),
                );
            }
        );

        $this->commands(
            [
                EmitEvents::class,
            ]
        );
    }
}
