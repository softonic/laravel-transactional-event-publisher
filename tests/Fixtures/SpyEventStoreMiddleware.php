<?php

namespace Softonic\TransactionalEventPublisher\Fixtures;

use Illuminate\Support\Facades\DB;
use Override;
use Softonic\TransactionalEventPublisher\Interfaces\EventMessageInterface;
use Softonic\TransactionalEventPublisher\Interfaces\EventStoreMiddlewareInterface;
use Throwable;

class SpyEventStoreMiddleware implements EventStoreMiddlewareInterface
{
    /**
     * @var EventMessageInterface[]
     */
    public array $storedMessages = [];

    /**
     * Transaction level observed on the default connection each time store() was called.
     *
     * @var int[]
     */
    public array $transactionLevelsWhenStoring = [];

    public bool $result = true;

    public ?Throwable $exceptionToThrow = null;

    #[Override]
    public function store(EventMessageInterface ...$messages): bool
    {
        $this->transactionLevelsWhenStoring[] = DB::connection()->transactionLevel();

        if ($this->exceptionToThrow instanceof Throwable) {
            throw $this->exceptionToThrow;
        }

        if ($this->result) {
            array_push($this->storedMessages, ...$messages);
        }

        return $this->result;
    }

    /**
     * @return string[]
     */
    public function storedEventTypes(): array
    {
        return array_map(
            static fn (EventMessageInterface $message): string => $message->toArray()['eventType'],
            $this->storedMessages
        );
    }
}
