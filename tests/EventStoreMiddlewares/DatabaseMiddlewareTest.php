<?php

namespace Softonic\TransactionalEventPublisher\EventStoreMiddlewares;

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Softonic\TransactionalEventPublisher\Interfaces\EventMessageInterface;
use Softonic\TransactionalEventPublisher\Models\DomainEvent;
use Softonic\TransactionalEventPublisher\TestCase;
use Softonic\TransactionalEventPublisher\ValueObjects\EventMessage;

class DatabaseMiddlewareTest extends TestCase
{
    #[Test]
    public function whenStoringAMessageShouldReturnTrue(): void
    {
        $this->createDomainEventsTable();

        $databaseMiddleware = new DatabaseMiddleware();

        self::assertTrue($databaseMiddleware->store($this->getOneMessage()));
        self::assertSame(1, DomainEvent::count());
    }

    #[Test]
    public function whenStoringMultipleMessagesShouldReturnTrue(): void
    {
        $this->createDomainEventsTable();

        $databaseMiddleware = new DatabaseMiddleware();

        self::assertTrue($databaseMiddleware->store($this->getOneMessage(), $this->getOneMessage()));
        self::assertSame(2, DomainEvent::count());
    }

    #[Test]
    public function whenStoringAMessageFailsShouldReturnFalse(): void
    {
        Log::shouldReceive('error');

        $databaseMiddleware = new DatabaseMiddleware();

        self::assertFalse($databaseMiddleware->store($this->getOneMessage()));
    }

    #[Test]
    public function whenStoringAMessageFailsShouldLogTheExceptionWithTheEventContext(): void
    {
        $message = $this->getOneMessage();

        $loggedMessage = null;
        $loggedContext = null;
        Log::shouldReceive('error')
            ->once()
            ->with(Mockery::capture($loggedMessage), Mockery::capture($loggedContext));

        $databaseMiddleware = new DatabaseMiddleware();

        self::assertFalse($databaseMiddleware->store($message));

        self::assertStringContainsString('domain_events', $loggedMessage);
        self::assertSame(QueryException::class, $loggedContext['exception']);
        self::assertStringContainsString('no such table: domain_events', $loggedContext['error']);
        self::assertSame(
            [
                [
                    'service'     => 'service',
                    'eventType'   => 'created',
                    'modelName'   => 'Model',
                    'eventName'   => 'ModelCreated',
                    'createdAt'   => '2026-08-04 10:00:00',
                    'sizeInBytes' => strlen(serialize($message)),
                ],
            ],
            $loggedContext['messages']
        );
    }

    #[Test]
    public function whenStoringMultipleMessagesFailsShouldLogEveryMessage(): void
    {
        $loggedContext = null;
        Log::shouldReceive('error')
            ->once()
            ->with(Mockery::any(), Mockery::capture($loggedContext));

        $databaseMiddleware = new DatabaseMiddleware();

        self::assertFalse($databaseMiddleware->store($this->getOneMessage(), $this->getOneMessage()));

        self::assertCount(2, $loggedContext['messages']);
    }

    #[Test]
    public function whenStoringAnOversizedMessageFailsShouldLogItsSizeInsteadOfItsPayload(): void
    {
        $payload = str_repeat('a', 100_000);
        $message = $this->getOneMessage($payload);

        $loggedMessage = null;
        $loggedContext = null;
        Log::shouldReceive('error')
            ->once()
            ->with(Mockery::capture($loggedMessage), Mockery::capture($loggedContext));

        $databaseMiddleware = new DatabaseMiddleware();

        $databaseMiddleware->store($message);

        self::assertStringNotContainsString($payload, $loggedMessage);
        self::assertStringNotContainsString($payload, json_encode($loggedContext));
        self::assertLessThan(1000, strlen($loggedContext['error']));
        self::assertSame(strlen(serialize($message)), $loggedContext['messages'][0]['sizeInBytes']);
    }

    private function getOneMessage(string $payload = 'value'): EventMessageInterface
    {
        return new EventMessage(
            service: 'service',
            eventType: 'created',
            modelName: 'Model',
            eventName: 'ModelCreated',
            payload: ['field' => $payload],
            createdAt: '2026-08-04 10:00:00',
        );
    }

    /**
     * The failure tests rely on the table not existing, so it is only created for the successful ones.
     */
    private function createDomainEventsTable(): void
    {
        Schema::create('domain_events', function (Blueprint $table): void {
            $table->increments('id');
            $table->text('message');
        });
    }
}
