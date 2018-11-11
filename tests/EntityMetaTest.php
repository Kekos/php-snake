<?php declare(strict_types=1);
namespace Kekos\PhpSnake\Tests;

use ReflectionProperty;
use Kekos\PhpSnake\EntityMeta;
use Kekos\PhpSnake\Exception\SnakeMetaException;
use Kekos\PhpSnake\Tests\Fixtures\BarEntity;
use Kekos\PhpSnake\Tests\Fixtures\FaultyArrayEntity;
use Kekos\PhpSnake\Tests\Fixtures\FaultyEntity;
use Kekos\PhpSnake\Tests\Fixtures\FooEntity;
use PHPUnit\Framework\TestCase;

class EntityMetaTest extends TestCase
{
    /** @var EntityMeta */
    private $meta;

    protected function setUp(): void
    {
        $this->meta = new EntityMeta(FooEntity::class);
    }

    public function testGetClassName(): void
    {
        $this->assertEquals(FooEntity::class, $this->meta->getClassName());
    }

    public function testGetTableName(): void
    {
        $this->assertEquals('foo_entity', $this->meta->getTableName());
    }

    public function testGetPrimaryKeyColumnsDefaultId(): void
    {
        $this->assertEquals(['id' => true], $this->meta->getPrimaryKeyColumns());
    }

    public function testGetPrimaryKeyColumnsWhenDefined(): void
    {
        $meta = new EntityMeta(BarEntity::class);

        $this->assertEquals([
            'bar_id' => false,
            'baz_id' => false,
        ], $meta->getPrimaryKeyColumns());
    }

    public function testGetPrimaryKeyColumnsThrowsOnNonStatic(): void
    {
        $this->expectException(SnakeMetaException::class);
        $this->expectExceptionMessage('Property must be static');

        $meta = new EntityMeta(FaultyEntity::class);
        $meta->getPrimaryKeyColumns();
    }

    public function testGetPrimaryKeyColumnsThrowsOnNonArray(): void
    {
        $this->expectException(SnakeMetaException::class);
        $this->expectExceptionMessage('expected array');

        $meta = new EntityMeta(FaultyArrayEntity::class);
        $meta->getPrimaryKeyColumns();
    }

    public function testHasAutoIncrementPrimary(): void
    {
        $this->assertEquals(true, $this->meta->hasAutoIncrementPrimary());
    }

    public function testHasNotAutoIncrementPrimary(): void
    {
        $meta = new EntityMeta(BarEntity::class);
        $this->assertEquals(false, $meta->hasAutoIncrementPrimary());
    }

    public function testGetDefaultColumns(): void
    {
        $this->assertEquals([
            'id',
            'name',
            'bar',
            'created_time',
        ], $this->meta->getDefaultColumns());
    }

    public function testGetColumnReflections(): void
    {
        $reflections = $this->meta->getColumnReflections();
        $expected = [
            'id',
            'name',
            'bar',
            'created_time',
        ];

        $this->assertCount(4, $reflections);

        foreach ($reflections as $i => $reflection) {
            $this->assertInstanceOf(ReflectionProperty::class, $reflection);
            $this->assertEquals($expected[$i], $reflection->getName());
        }
    }

    public function testGetColumnsWithValues(): void
    {
        $entity = new FooEntity();

        $this->assertEquals([
            'id' => null,
            'name' => null,
            'bar' => null,
            'created_time' => null,
        ], $this->meta->getColumnsWithValues($entity));
    }

    public function testPrimaryColumnsWithValues(): void
    {
        $entity = new FooEntity();
        $id = 42;

        $reflection_prop = new ReflectionProperty(FooEntity::class, 'id');
        $reflection_prop->setAccessible(true);
        $reflection_prop->setValue($entity, $id);

        $this->assertEquals([
            'id' => $id,
        ], $this->meta->getPrimaryColumnsWithValues($entity));
    }

    public function testPrimaryColumnsWithValuesEmpty(): void
    {
        $entity = new FooEntity();

        $this->assertEquals([], $this->meta->getPrimaryColumnsWithValues($entity));
    }
}
