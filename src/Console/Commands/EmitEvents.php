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
use Softonic\TransactionalEventPublisher\Models\DomainEventsCursor;

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
        {--batchSize=100 : Indicate the amount of events to be sent per publish. Increase for higher throughput}
        {--allEvents : Option to send all the events from the beginning by resetting the cursor}';

    protected $description = 'Continuously emits domain events in batches';

    public EventStoreMiddlewareContract $eventPublisherMiddleware;

    public DomainEventsCursor $cursor;

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
        $resetCursor = $this->option('allEvents');

        $this->cursor = $this->getInitialCursor($resetCursor);

        $this->sendBatches();
    }

    private function getInitialCursor(bool $resetCursor): DomainEventsCursor
    {
        $cursor = DomainEventsCursor::first();

        if (empty($cursor)) {
            $cursor = new DomainEventsCursor(['last_id' => 0]);
            $cursor->save();

            return $cursor;
        }

        if ($resetCursor) {
            $cursor->update(['last_id' => 0]);
        }

        return $cursor;
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
        $lastId = $this->cursor->last_id;

        try {
            $events = DomainEvent::on($this->databaseConnection)->where('id', '>', $lastId)->cursor();
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

        if ($eventMessagesCount !== $this->batchSize) {
            $this->checkCursorConsistencyWithEvents($eventMessagesCount, $lastId);
        }

        if (!$this->eventPublisherMiddleware->store(...$eventMessages)) {
            $errorMessage = "The events couldn't be sent. Retrying...";
            Log::alert($errorMessage, ['eventMessages' => $eventMessages->toArray()]);

            throw new RuntimeException($errorMessage);
        }

        try {
            $this->cursor->update(['last_id' => $lastId]);
        } catch (Exception $e) {
            $this->cursor->discardChanges();

            throw $e;
        }

        Log::info("Published {$eventMessagesCount} events, last event ID published: {$lastId}");

        $this->eventsProcessed = true;
        $this->attemptForErrors = $this->attemptForNoEvents = 1;
    }

    protected function checkCursorConsistencyWithEvents(int $eventMessagesCount, int $lastId): void
    {
        $previousLastId = $this->cursor->last_id;

        if (!$this->isCursorConsistentWithMessages($previousLastId, $eventMessagesCount, $lastId)) {
            $errorMessage = 'Mismatch in the events to send. Retrying...';
            Log::warning(
                $errorMessage,
                compact('previousLastId', 'eventMessagesCount', 'lastId')
            );

            throw new RuntimeException($errorMessage);
        }
    }

    protected function isCursorConsistentWithMessages(int $previousLastId, int $eventMessagesCount, int $lastId): bool
    {
        return $previousLastId + $eventMessagesCount === $lastId;
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
