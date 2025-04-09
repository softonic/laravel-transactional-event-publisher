<?php

namespace Softonic\TransactionalEventPublisher;

use Illuminate\Support\ServiceProvider as LaravelServiceProvider;
use Override;
use Softonic\TransactionalEventPublisher\Console\Commands\EmitEvents;
use Softonic\TransactionalEventPublisher\EventStoreMiddlewares\AmqpMiddleware;
use Softonic\TransactionalEventPublisher\Factories\AmqpMessageFactory;
use Softonic\TransactionalEventPublisher\Observers\ModelObserver;
use Softonic\TransactionalEventPublisher\Services\Amqp;

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
    public function boot(): void
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
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/' . $this->packageName . '.php', $this->packageName);

        $this->app->bind(Amqp::class, function () {
            $amqp = new Amqp(config('transactional-event-publisher.properties.amqp'));
            $amqp->setUp();
            return $amqp;
        });

        $this->app->bind(AmqpMiddleware::class, fn (): AmqpMiddleware => new AmqpMiddleware(
            resolve(AmqpMessageFactory::class),
            resolve(Amqp::class),
        ));

        $this->app->bind(ModelObserver::class, function (): ModelObserver {
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
                resolve(config('transactional-event-publisher.messageBuilder'))
            );
        });

        $this->app->bindMethod(
            'Softonic\TransactionalEventPublisher\Console\Commands\EmitEvents@handle',
            fn ($job) => $job->handle(
                resolve(config('transactional-event-publisher.event_publisher_middleware')),
            )
        );

        $this->commands(
            [
                EmitEvents::class,
            ]
        );
    }
}
