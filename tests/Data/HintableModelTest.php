<?php

declare(strict_types=1);

namespace Mvorisek\Atk4\Hintable\Tests\Data;

use Atk4\Core\AtkPhpunit;
use Atk4\Data\Exception;

/**
 * @coversDefaultClass \Mvorisek\Atk4\Hintable\Data\HintableModelTrait
 */
class HintableModelTest extends AtkPhpunit\TestCase
{
    public function testFieldName(): void
    {
        $model = new Model\Simple();
        $this->assertSame('simple', $model->table);
        $this->assertSame('x', $model->fieldName()->x);
        $this->assertSame('x', Model\Simple::hinting()->fieldName()->x);

        $model = new Model\Standard();
        $this->assertSame('prefix_standard', $model->table);
        $this->assertSame('x', $model->fieldName()->x);
        $this->assertSame('yy', $model->fieldName()->y);
        $this->assertSame('id', $model->fieldName()->id);
        $this->assertSame('name', $model->fieldName()->_name);
        $this->assertSame('simpleOne', $model->fieldName()->simpleOne);
        $this->assertSame('simpleMany', $model->fieldName()->simpleMany);
    }

    public function testFieldNameUndeclaredException(): void
    {
        $model = new Model\Simple();
        $this->expectException(Exception::class);
        $model->fieldName()->y; // @phpstan-ignore-line
    }

    protected function createDatabaseForRefTest(): \Atk4\Data\Persistence
    {
        $db = new \Atk4\Data\Persistence\Array_();

        $simpleA = (new Model\Simple($db))
            ->set(Model\Simple::hinting()->fieldName()->x, 'a')
            ->save();
        $simpleB1 = (new Model\Simple($db))
            ->set(Model\Simple::hinting()->fieldName()->x, 'b1')
            ->save();
        $simpleB2 = (new Model\Simple($db))
            ->set(Model\Simple::hinting()->fieldName()->x, 'b2')
            ->save();

        $standardTemplate = (new Model\Standard($db))
            ->set(Model\Standard::hinting()->fieldName()->x, 'xx')
            ->set(Model\Standard::hinting()->fieldName()->y, 'yy')
            ->set(Model\Standard::hinting()->fieldName()->_name, 'zz')
            ->set(Model\Standard::hinting()->fieldName()->dtImmutable, new \DateTime('2000-1-1 12:00:00'))
            ->set(Model\Standard::hinting()->fieldName()->dtInterface, new \DateTimeImmutable('2000-2-1 12:00:00'))
            ->set(Model\Standard::hinting()->fieldName()->dtMulti, new \DateTimeImmutable('2000-3-1 12:00:00'));
        $standardA = (clone $standardTemplate)
            ->set(Model\Standard::hinting()->fieldName()->simpleOneId, $simpleA->id)
            ->save();
        $standardB = (clone $standardTemplate)
            ->set(Model\Standard::hinting()->fieldName()->simpleOneId, $simpleB2->id)
            ->save();

        $simpleA
            ->set(Model\Simple::hinting()->fieldName()->refId, $standardA->id)
            ->save();
        $simpleB1
            ->set(Model\Simple::hinting()->fieldName()->refId, $standardB->id)
            ->save();
        $simpleB2
            ->set(Model\Simple::hinting()->fieldName()->refId, $standardB->id)
            ->save();

        return $db;
    }

    public function testRefBasic(): void
    {
        $db = $this->createDatabaseForRefTest();

        $model = new Model\Simple($db);
        $this->assertSame(1, (clone $model)->load(1)->ref->id);
        $this->assertSame(2, (clone $model)->load(2)->ref->id);
        $this->assertSame(2, (clone $model)->load(3)->ref->id);
    }

    public function testRefNoData(): void
    {
        $model = new Model\Standard();
        $model->invokeInit();
        $this->assertInstanceOf(Model\Simple::class, $model->simpleOne);

        // TODO seems like a bug in atk4/data
        $this->markTestSkipped(); // @phpstan-ignore-next-line
        $model = new Model\Standard();
        $model->invokeInit();
        $this->assertInstanceOf(Model\Simple::class, $model->simpleMany);
    }

    public function testRefOne(): void
    {
        $db = $this->createDatabaseForRefTest();

        $model = new Model\Standard($db);
        $this->assertInstanceOf(Model\Simple::class, $model->simpleOne);
        $this->assertSame(1, (clone $model)->simpleOne->loadAny()->id);
        $this->assertSame(3, (clone $model)->load(2)->simpleOne->id);
    }

    public function testRefMany(): void
    {
        $db = $this->createDatabaseForRefTest();

        $model = new Model\Standard($db);
        $this->assertInstanceOf(Model\Simple::class, $model->simpleMany);
        $this->assertSame(1, $model->simpleMany->loadAny()->id);
//        $this->assertSame(2, $model->load(2)->simpleMany->loadAny()->id);

        $this->assertSame([2 => 2, 3 => 3], array_map(function (Model\Simple $model) {
            return $model->id;
        }, iterator_to_array($model->load(2)->simpleMany)));
    }

    public function testRefManyIsUnload(): void
    {
        $db = $this->createDatabaseForRefTest();
        $model = new Model\Standard($db);
        $this->assertNull($model->load(2)->simpleMany->id);
    }

    public function testRefOneLoadOneException(): void
    {
        $db = $this->createDatabaseForRefTest();
        $model = new Model\Standard($db);
        $this->expectException(Exception::class);
        $model->simpleOne->loadOne();
    }

    public function testRefManyLoadOneException(): void
    {
        $db = $this->createDatabaseForRefTest();
        $model = new Model\Standard($db);
        $this->expectException(Exception::class);
        $model->simpleMany->loadOne();
    }

    public function testPhpstanModelIteratorAggregate(): void
    {
        $db = $this->createDatabaseForRefTest();
        $model = new Model\Simple($db);
        $this->assertIsString((clone $model)->loadAny()->x);
        foreach ($model as $modelItem) {
            $this->assertIsString($modelItem->x);
        }
    }
}
