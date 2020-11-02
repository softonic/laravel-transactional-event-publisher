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
        {--unbufferedConnection= : Indicate the unbuffered connection (MySQL) for large amount of events}
        {--batchSize=1 : Indicate the amount of events to be sent per publish. Increase for higher throughput}';

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
        $batchSize          = $this->option('batchSize');
        $totalEventsToEmit  = DomainEvent::count();
        $bar                = $this->output->createProgressBar($totalEventsToEmit);

        $bar->start();
        $bar->minSecondsBetweenRedraws(1);
        $bar->setFormat('%current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s%');

        DomainEvent::on($databaseConnection)->cursor()
            ->chunk($batchSize)
            ->each(
                function ($domainEvents) use ($queueConnection, $bar, $batchSize) {
                    SendDomainEvents::dispatch(SendDomainEvents::NO_RETRIES, ...$domainEvents->pluck('message'))
                        ->onConnection($queueConnection);
                    $bar->advance($batchSize);
                }
            );

        $bar->finish();
    }
}
