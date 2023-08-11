<?php

declare(strict_types=1);

namespace Softonic\TransactionalEventPublisher\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\LazyCollection;
use RuntimeException;
use Softonic\TransactionalEventPublisher\Contracts\EventStoreMiddlewareContract;
use Softonic\TransactionalEventPublisher\Models\DomainEvent;

class EmitEvents extends Command
{
    /**
     * Max delay for errors retry in seconds (1 minute).
     */
    private const MAX_DELAY_FOR_ERRORS = 60;

    /**
     * Max delay for no events retry in microseconds (1 second).
     */
    private const MAX_DELAY_FOR_NO_EVENTS = 1_000_000;

    /**
     * Base delay for no events retry in microseconds (1 millisecond).
     */
    private const BASE_DELAY_FOR_NO_EVENTS = 1000;

    protected $signature = 'event-sourcing:emit
        {--dbConnection=mysql : Indicate the database connection to use (MySQL unbuffered for better performance when large amount of events)}
        {--batchSize=100 : Indicate the amount of events to be sent per publish. Increase for higher throughput}';

    protected $description = 'Continuously emits domain events in batches';

    public EventStoreMiddlewareContract $eventPublisherMiddleware;

    public string $databaseConnection;

    public int $batchSize;

    public int $attemptForErrors = 1;

    public int $attemptForNoEvents = 1;

    private bool $eventsProcessed;

    public function handle(EventStoreMiddlewareContract $eventPublisherMiddleware): void
    {
        $this->eventPublisherMiddleware = $eventPublisherMiddleware;

        $this->databaseConnection = $this->option('dbConnection');
        $this->batchSize = (int)$this->option('batchSize');

        $this->sendBatches();
    }

    protected function sendBatches(): void
    {
        while (true) {
            $this->sendBatch();
        }
    }

    public function sendBatch(): void
    {
        $this->eventsProcessed = false;

        try {
            $events = DomainEvent::on($this->databaseConnection)->cursor();
        } catch (Exception $e) {
            $this->waitExponentialBackOffForErrors();

            return;
        }

        try {
            $events->chunk($this->batchSize)->each($this->sendEvents(...));
        } catch (Exception $e) {
            $this->waitExponentialBackOffForErrors();

            return;
        }

        if (!$this->eventsProcessed) {
            $this->waitExponentialBackOffForNoEvents();
        }
    }

    private function sendEvents(LazyCollection $events): void
    {
        // Transform the events to the format expected by the event publisher
        $eventMessages = $events->pluck('message');

        $lastId = $events->max('id');
        $eventMessagesCount = count($eventMessages);

        if (!$this->eventPublisherMiddleware->store(...$eventMessages)) {
            $errorMessage = "The events couldn't be sent. Retrying...";
            Log::alert($errorMessage, ['eventMessages' => $eventMessages->toArray()]);

            throw new RuntimeException($errorMessage);
        }

        Log::info("Published {$eventMessagesCount} events, last event ID published: {$lastId}");

        $this->eventsProcessed = true;
        $this->attemptForErrors = $this->attemptForNoEvents = 1;

        $events->each->delete();
        Log::debug("Deleted {$eventMessagesCount} events, last event ID deleted: {$lastId}");
    }

    private function waitExponentialBackOffForErrors(): void
    {
        $delay = pow(2, $this->attemptForErrors - 1);
        $delay = min($delay, self::MAX_DELAY_FOR_ERRORS);

        ++$this->attemptForErrors;

        sleep($delay);
    }

    private function waitExponentialBackOffForNoEvents(): void
    {
        $delay = self::BASE_DELAY_FOR_NO_EVENTS * pow(2, $this->attemptForNoEvents - 1);
        $delay = min($delay, self::MAX_DELAY_FOR_NO_EVENTS);

        ++$this->attemptForNoEvents;

        usleep($delay);
    }
}
