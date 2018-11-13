<?php declare(strict_types=1);
namespace Kekos\PhpSnake\Tests;

use RuntimeException;
use Kekos\PhpSnake\EntityManager;
use Kekos\PhpSnake\EntityMeta;
use Kekos\PhpSnake\EntityPersister;
use Kekos\PhpSnake\Tests\Fixtures\BarEntity;
use Kekos\PhpSnake\Tests\Fixtures\FooEntity;

class EntityManagerTest extends ConnectionTestCase
{
    /** @var EntityManager */
    private $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = new EntityManager($this->conn);
    }

    // TODO: https://matthiasnoback.nl/2018/10/test-driving-repository-classes-part-2-storing-and-retrieving-entities/

    public function testFind(): void
    {
        $this->createDatabaseFixturesFoo();

        $load_id = 1;
        /** @var FooEntity|null $result */
        $result = $this->manager->find(FooEntity::class, $load_id);

        if ($result === null) {
            throw new RuntimeException('Entity not found');
        }

        $expected = self::ROW_FIXTURES_FOO[0];
        $this->assertEquals($load_id, $result->getId());
        $this->assertEquals($expected[0], $result->getName());
        $this->assertEquals($expected[1], $result->getBar());
        $this->assertStringMatchesFormat('%d-%d-%d %d:%d:%d', $result->getCreatedTime());

        /** @var FooEntity|null $result */
        $second_result = $this->manager->find(FooEntity::class, $load_id);
        $this->assertSame($result, $second_result);
    }

    public function testFindCompoundPrimary(): void
    {
        $this->createDatabaseFixturesBar();

        $load_id = [
            'baz_id' => 2,
            'bar_id' => 13,
        ];
        /** @var BarEntity|null $result */
        $result = $this->manager->find(BarEntity::class, $load_id);

        if ($result === null) {
            throw new RuntimeException('Entity not found');
        }

        $expected = self::ROW_FIXTURES_BAR[0];
        $this->assertEquals($expected[0], $result->getBarId());
        $this->assertEquals($expected[1], $result->getBazId());
        $this->assertEquals($expected[2], $result->getInfo());

        /** @var BarEntity|null $result */
        $second_result = $this->manager->find(BarEntity::class, $load_id);
        $this->assertSame($result, $second_result);

        $load_id = [
            'bar_id' => 13,
            'baz_id' => 2,
        ];

        $third_result = $this->manager->getById($load_id, BarEntity::class);
        $this->assertSame($result, $third_result);
    }

    public function testPersistNew(): void
    {
        $name = 'foo test persist name';

        $entity = new FooEntity();
        $entity->setName($name);
        $entity->setCreatedTime(date('Y-m-d H:i:s'));

        $this->manager->persist($entity);
        $this->manager->flush();

        /** @var FooEntity|null $result */
        $result = $this->manager->find(FooEntity::class, $entity->getId());

        if ($result === null) {
            throw new RuntimeException('Entity not found');
        }

        $this->assertEquals($name, $result->getName());
    }

    public function testGetEntityStateNew(): void
    {
        $entity = new FooEntity();

        $state = $this->manager->getEntityState($entity);

        $this->assertEquals(EntityManager::STATE_NEW, $state);
    }

    public function testGetEntityStateNewWithCheck(): void
    {
        $entity = new BarEntity();
        $entity->setBarId(42);
        $entity->setBazId(9);

        $state = $this->manager->getEntityState($entity);
        $this->assertEquals(EntityManager::STATE_NEW, $state);
    }

    public function testGetEntityStateDetached(): void
    {
        $this->createDatabaseFixturesFoo();

        $entity = $this->manager->getEntityPersister(FooEntity::class)->load([
            'id' => 1,
        ]);

        if ($entity === null) {
            throw new RuntimeException('Entity not found');
        }

        $state = $this->manager->getEntityState($entity);
        $this->assertEquals(EntityManager::STATE_DETACHED, $state);
    }

    public function testGetEntityMeta(): void
    {
        $meta = $this->manager->getEntityMeta(FooEntity::class);
        $expected = new EntityMeta(FooEntity::class);

        $this->assertEquals($expected, $meta);

        $meta2 = $this->manager->getEntityMeta(FooEntity::class);
        $this->assertSame($meta2, $meta);
    }

    public function testGetEntityPersister(): void
    {
        $persister = $this->manager->getEntityPersister(FooEntity::class);
        $expected = new EntityPersister(new EntityMeta(FooEntity::class), $this->conn);

        $this->assertEquals($expected, $persister);

        $persister2 = $this->manager->getEntityPersister(FooEntity::class);
        $this->assertSame($persister2, $persister);
    }
}
