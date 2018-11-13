<?php declare(strict_types=1);
namespace Kekos\PhpSnake\Tests;

use PDO;
use Kekos\PhpSnake\EntityMeta;
use Kekos\PhpSnake\EntityPersister;
use Kekos\PhpSnake\Tests\Fixtures\FooEntity;
use QueryBuilder\QueryBuilders\Select;

class EntityPersisterTest extends ConnectionTestCase
{

    /** @var EntityPersister */
    private $persister;

    protected function setUp(): void
    {
        parent::setUp();

        $this->persister = new EntityPersister(
            new EntityMeta(FooEntity::class),
            $this->conn
        );
    }

    public function testAddInsert(): void
    {
        $entity = $this->getFilledEntity();
        $oid = spl_object_id($entity);

        $this->persister->addInsert($entity);

        $this->assertAttributeEquals([
            $oid => $entity,
        ], 'queued_inserts', $this->persister);
    }

    public function testExecuteInserts(): void
    {
        $entity = $this->getFilledEntity();
        $oid = spl_object_id($entity);

        $this->persister->addInsert($entity);

        $this->assertEquals(0, $this->getTableCount());

        $generated_ids = $this->persister->executeInserts();

        $this->assertEquals(1, $this->getTableCount());
        $this->assertEquals([
            $oid => 1,
        ], $generated_ids);
        $this->assertInternalType('int', current($generated_ids));

        $this->assertAttributeEquals([], 'queued_inserts', $this->persister);
    }

    public function testUpdate(): void
    {
        $entity = $this->getFilledEntity();

        $this->persister->addInsert($entity);
        $generated_ids = $this->persister->executeInserts();

        $this->setEntityWithId($entity, intval(current($generated_ids)));

        $expected = [
            'name' => 'edit name',
            'bar' => 'not null',
        ];
        $entity->setName($expected['name']);
        $entity->setBar($expected['bar']);

        $this->persister->update($entity);

        $stmt = $this->conn->prepare("SELECT `name`, `bar` FROM `foo_entity` WHERE `id` = ?");
        $stmt->execute([$entity->getId()]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertEquals($expected, $result);
    }

    public function testDelete(): void
    {
        $entity = $this->getFilledEntity();

        $this->persister->addInsert($entity);
        $generated_ids = $this->persister->executeInserts();

        $this->setEntityWithId($entity, intval(current($generated_ids)));

        $this->assertEquals(1, $this->getTableCount());
        $this->persister->delete($entity);
        $this->assertEquals(0, $this->getTableCount());
    }

    public function testExistsPositive(): void
    {
        $this->createDatabaseFixturesFoo();

        $this->assertTrue($this->persister->exists(['id' => 1]));
    }

    public function testExistsNegative(): void
    {
        $this->createDatabaseFixturesFoo();

        $this->assertFalse($this->persister->exists(['id' => 4]));
    }

    public function testLoad(): void
    {
        $this->createDatabaseFixturesFoo();

        $load_id = 1;
        /** @var FooEntity $result */
        $result = $this->persister->load(['id' => $load_id]);

        $this->assertInstanceOf(FooEntity::class, $result);

        $expected = self::ROW_FIXTURES_FOO[0];
        $this->assertEquals($load_id, $result->getId());
        $this->assertEquals($expected[0], $result->getName());
        $this->assertEquals($expected[1], $result->getBar());
        $this->assertStringMatchesFormat('%d-%d-%d %d:%d:%d', $result->getCreatedTime());
    }

    public function testLoadNotFound(): void
    {
        $this->createDatabaseFixturesFoo();

        $result = $this->persister->load(['id' => 42]);

        $this->assertNull($result);
    }

    public function testLoadAll(): void
    {
        $this->createDatabaseFixturesFoo();

        /** @var FooEntity[]|array $results */
        $results = $this->persister->loadAll(function (Select $qb): void {
            $qb->orderby('id');
        });

        $this->assertCount(count(self::ROW_FIXTURES_FOO), $results);

        foreach (self::ROW_FIXTURES_FOO as $i => $expected) {
            $result = $results[$i];
            $this->assertInstanceOf(FooEntity::class, $result);
            $this->assertEquals($expected[0], $result->getName());
            $this->assertEquals($expected[1], $result->getBar());
            $this->assertStringMatchesFormat('%d-%d-%d %d:%d:%d', $result->getCreatedTime());
        }
    }

    public function testGetInsertSql(): void
    {
        $expected_sql = "INSERT INTO `foo_entity` (`name`, `bar`, `created_time`)\n\tVALUES (?, ?, ?)";

        $sql = $this->persister->getInsertSql();

        $this->assertEquals($expected_sql, $sql);
    }

    public function testGetUpdateSql(): void
    {
        $entity = $this->getFilledEntity();
        $this->setEntityWithId($entity, 42);

        $expected_sql = "UPDATE `foo_entity`\n\t"
            . "SET\n\t\t`name` = ?,\n\t\t`bar` = ?,\n\t\t`created_time` = ?\n\t"
            . "WHERE `id` = ?";

        $expected_params = [
            $entity->getName(),
            $entity->getBar(),
            $entity->getCreatedTime(),
            $entity->getId(),
        ];

        $sql = $this->persister->getUpdateSql($entity);

        $this->assertEquals($expected_sql, (string) $sql);
        $this->assertEquals($expected_params, $sql->getParams());
    }

    public function testGetDeleteSql(): void
    {
        $entity = new FooEntity();
        $this->setEntityWithId($entity, 42);

        $expected_sql = "DELETE FROM `foo_entity`\n\tWHERE `id` = ?";

        $expected_params = [
            $entity->getId(),
        ];

        $sql = $this->persister->getDeleteSql($entity);

        $this->assertEquals($expected_sql, (string) $sql);
        $this->assertEquals($expected_params, $sql->getParams());
    }

    public function testGetSelectQueryBuilder(): void
    {
        $expected_sql = "SELECT *\n\tFROM `foo_entity`\n";

        $qb = $this->persister->getSelectQueryBuilder();
        $this->assertInstanceOf(Select::class, $qb);

        $sql = $qb->toSql();
        $this->assertEquals($expected_sql, (string) $sql);
        $this->assertEquals([], $sql->getParams());
    }
}
