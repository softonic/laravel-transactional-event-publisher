<?php

namespace Softonic\TransactionalEventPublisher\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Softonic\TransactionalEventPublisher\Jobs\SendDomainEvents;
use Softonic\TransactionalEventPublisher\Model\DomainEvent;

class EmitAllEvents extends Command
{
    /**
     * Create a new command instance.
     *
     */
    private const CHUNK_SIZE = 1000;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'event-sourcing:emit-all {queueConnection=database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate all the needed jobs for all the domain events';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $queueConnection       = $this->argument('queueConnection');
        $page                  = 1;
        $lastDomainEventToEmit = DomainEvent::limit(1)
            ->orderBy('id', 'DESC')
            ->first();

        do {
            $domainEvents = $this->getDomainEvents($page++, $lastDomainEventToEmit->id);
            $domainEvents->each(function ($domainEvent) use ($queueConnection) {
                SendDomainEvents::dispatch($domainEvent->message)->onConnection($queueConnection);
            });
        } while ($domainEvents->isNotEmpty());
    }

    public function getDomainEvents($page, $untilId): Collection
    {
        return DomainEvent::where('id', '<=', $untilId)
            ->orderBy('id', 'ASC')
            ->offset(($page - 1) * self::CHUNK_SIZE)
            ->limit(self::CHUNK_SIZE)
            ->get();
    }
}
