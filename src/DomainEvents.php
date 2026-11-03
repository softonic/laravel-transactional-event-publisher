<?php

declare(strict_types=1);

namespace Softonic\TransactionalEventPublisher;

/**
 * Process-wide switch to stop storing domain events, e.g. during bulk synchronisations.
 *
 * The PublishesDomainEvents trait checks it each time an event is going to be stored, so it does not depend on when the
 * models are booted. While it is disabled no event is stored, and the writes of the models still run in their own
 * transaction.
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
