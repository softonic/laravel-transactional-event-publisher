<?php

declare(strict_types=1);

namespace Softonic\TransactionalEventPublisher\Concerns;

use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Softonic\TransactionalEventPublisher\Fixtures\DomainEventsTestCase;
use Softonic\TransactionalEventPublisher\Fixtures\OtherPublishingSubModel;
use Softonic\TransactionalEventPublisher\Fixtures\PublishingChildModel;
use Softonic\TransactionalEventPublisher\Fixtures\PublishingModel;
use Softonic\TransactionalEventPublisher\Fixtures\PublishingSubModel;
use Softonic\TransactionalEventPublisher\Fixtures\RedeclaringPublishingSubModel;
use Softonic\TransactionalEventPublisher\Interfaces\EventMessageInterface;

/**
 * Declaring the trait on a base model is enough for every subclass to publish its own events, once.
 */
class PublishesDomainEventsInheritanceTest extends DomainEventsTestCase
{
    public static function subclassProvider(): array
    {
        return [
            'subclass of an abstract base model' => [PublishingSubModel::class, 1],
            'another subclass of the same base model' => [OtherPublishingSubModel::class, 1],
            // Its copy of the trait wraps the write and calls the copy of the base model, which wraps it again.
            'subclass re-declaring the trait' => [RedeclaringPublishingSubModel::class, 2],
            'child of a concrete model' => [PublishingChildModel::class, 1],
        ];
    }

    /**
     * @param class-string<Model> $modelClass
     */
    #[Test]
    #[DataProvider('subclassProvider')]
    public function whenTheTraitIsInheritedEachWriteStoresExactlyOneEventWithTheSubclassName(
        string $modelClass,
        int $transactionLevelWhenStoring
    ): void {
        $model = $modelClass::create(['id' => 1, 'name' => 'created']);
        $model->name = 'updated';
        $model->save();

        $model->delete();

        self::assertSame(['created', 'updated', 'deleted'], $this->middleware->storedEventTypes());
        self::assertSame(
            [$modelClass, $modelClass, $modelClass],
            $this->storedModelNames()
        );
        self::assertSame(3, $this->builder->buildCalls);
        self::assertSame(
            array_fill(0, 3, $transactionLevelWhenStoring),
            $this->middleware->transactionLevelsWhenStoring
        );
        self::assertSame(0, $this->transactionLevel());
    }

    #[Test]
    public function whenSeveralSubclassesOfTheSameBaseModelAreWrittenEachPublishesWithItsOwnName(): void
    {
        PublishingSubModel::create(['id' => 1, 'name' => 'sub']);
        OtherPublishingSubModel::create(['id' => 2, 'name' => 'other sub']);

        RedeclaringPublishingSubModel::create(['id' => 3, 'name' => 'redeclaring sub']);

        self::assertSame(['created', 'created', 'created'], $this->middleware->storedEventTypes());
        self::assertSame(
            [PublishingSubModel::class, OtherPublishingSubModel::class, RedeclaringPublishingSubModel::class],
            $this->storedModelNames()
        );
        self::assertSame(3, $this->builder->buildCalls);
    }

    #[Test]
    public function whenAConcreteParentAndItsChildAreBothWrittenEachPublishesOnceWithItsOwnName(): void
    {
        $parent = PublishingModel::create(['id' => 1, 'name' => 'parent']);
        $child = PublishingChildModel::create(['id' => 2, 'name' => 'child']);
        $parent->increment('counter');
        $child->increment('counter');
        $parent->delete();

        $child->delete();

        self::assertSame(
            ['created', 'created', 'updated', 'updated', 'deleted', 'deleted'],
            $this->middleware->storedEventTypes()
        );
        self::assertSame(
            [
                PublishingModel::class,
                PublishingChildModel::class,
                PublishingModel::class,
                PublishingChildModel::class,
                PublishingModel::class,
                PublishingChildModel::class,
            ],
            $this->storedModelNames()
        );
        self::assertSame(6, $this->builder->buildCalls);
        self::assertSame(0, $this->transactionLevel());
    }

    /**
     * @return string[]
     */
    private function storedModelNames(): array
    {
        return array_map(
            static fn (EventMessageInterface $message): string => $message->toArray()['modelName'],
            $this->middleware->storedMessages
        );
    }
}
