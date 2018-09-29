<?php declare(strict_types=1);
namespace Kekos\PhpSnake\Tests;

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

    public function testGetTableName(): void
    {
        $this->assertEquals('foo_entity', $this->meta->getTableName());
    }

    public function testGetPrimaryKeyColumnsDefaultId(): void
    {
        $this->assertEquals(['id'], $this->meta->getPrimaryKeyColumns());
    }

    public function testGetPrimaryKeyColumnsWhenDefined(): void
    {
        $meta = new EntityMeta(BarEntity::class);

        $this->assertEquals([
            'bar_id',
            'baz_id',
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
}
