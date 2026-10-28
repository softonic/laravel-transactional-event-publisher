<?php

declare(strict_types=1);

namespace Softonic\TransactionalEventPublisher;

/**
 * Process-wide switch to stop storing domain events, e.g. during bulk synchronisations.
 *
 * It is checked when each event is going to be stored, so it does not depend on when the models boot. While it is
 * disabled the writes of models using the PublishesDomainEvents trait still run in their own transaction, and the
 * deprecated ModelObserver neither opens transaction levels nor stores events.
 */
final class DomainEvents
{
    private static bool $enabled = true;

    public static function disable(): void
    {
        self::$enabled = false;
    }

    public static function enable(): void
    {
        self::$enabled = true;
    }

    public static function isEnabled(): bool
    {
        return self::$enabled;
    }

    /**
     * Runs the callback with publishing disabled, restoring the previous state afterwards.
     *
     * @template TReturn
     *
     * @param callable(): TReturn $callback
     *
     * @return TReturn
     */
    public static function withoutPublishing(callable $callback): mixed
    {
        $wasEnabled = self::$enabled;
        self::$enabled = false;

        try {
            return $callback();
        } finally {
            self::$enabled = $wasEnabled;
        }
    }
}
