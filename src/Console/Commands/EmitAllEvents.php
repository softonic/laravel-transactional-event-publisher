<?php

namespace Softonic\TransactionalEventPublisher\Console\Commands;

use Illuminate\Console\Command;
use Softonic\TransactionalEventPublisher\Jobs\SendDomainEvents;
use Softonic\TransactionalEventPublisher\Model\DomainEvent;

class EmitAllEvents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'event-sourcing:emit-all
        {queueConnection=database : Queue connection to be used to send the messages}
        {--unbufferedConnection= : Indicate the unbuffered connection (MySQL) for large amount of events}';

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
        $queueConnection    = $this->argument('queueConnection');
        $databaseConnection = $this->option('unbufferedConnection');
        $totalEventsToEmit  = DomainEvent::count();
        $bar                = $this->output->createProgressBar($totalEventsToEmit);

        $bar->start();
        $bar->minSecondsBetweenRedraws(1);

        DomainEvent::on($databaseConnection)->cursor()
            ->each(
                function ($domainEvent) use ($queueConnection, $bar) {
                    SendDomainEvents::dispatch($domainEvent->message)
                        ->onConnection($queueConnection);
                    $bar->advance();
                }
            );

        $bar->finish();
    }
}
