<?php declare(strict_types=1);
namespace Kekos\PhpSnake\Tests;

use PDO;
use ReflectionProperty;
use RuntimeException;
use Kekos\PhpSnake\Connection;
use Kekos\PhpSnake\EntityMeta;
use Kekos\PhpSnake\EntityPersister;
use Kekos\PhpSnake\Tests\Fixtures\FooEntity;
use PHPUnit\Framework\TestCase;
use QueryBuilder\MySqlAdapter;
use QueryBuilder\QueryBuilder;
use QueryBuilder\QueryBuilders\Select;

class EntityPersisterTest extends TestCase
{
    /** @var EntityPersister */
    private $persister;
    /** @var Connection */
    private $conn;

    public static function setUpBeforeClass(): void
    {
        QueryBuilder::setAdapter(new MySqlAdapter());
    }

    protected function setUp(): void
    {
        $this->conn = new Connection($_ENV['DB_DSN'], $_ENV['DB_USERNAME'], $_ENV['DB_PASSWORD']);
        $this->persister = new EntityPersister(
            new EntityMeta(FooEntity::class),
            $this->conn
        );

        $this->conn->exec("CREATE TEMPORARY TABLE `foo_entity` (
              `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `name` VARCHAR(64) NOT NULL,
              `bar` VARCHAR(64) NULL,
              `created_time` DATETIME NOT NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");
    }

    protected function tearDown(): void
    {
        $this->conn->exec("DROP TABLE `foo_entity`");
    }

    private function createDatabaseFixtures(): void
    {
        $stmt = $this->conn->prepare("INSERT INTO `foo_entity` (`name`, `bar`, `created_time`) VALUES (?, ?, NOW())");

        $fixtures = [
            ['foo1', null],
            ['foo2', 'bar2'],
            ['foo3', 'bar3'],
        ];

        foreach ($fixtures as $fixture) {
            $stmt->execute($fixture);
        }
    }

    private function getTableCount(): int
    {
        $stmt = $this->conn->query("SELECT COUNT(*) AS `cnt` FROM `foo_entity`");
        if (is_bool($stmt)) {
            throw new RuntimeException('Selecting count from foo_entity returned error');
        }

        return $stmt->fetchObject()->cnt;
    }

    private static function getFilledEntity(): FooEntity
    {
        $entity = new FooEntity();
        $entity
            ->setName('name')
            ->setBar(null)
            ->setCreatedTime(date('Y-m-d'));

        return $entity;
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

    public function testLoad(): void
    {
        $this->createDatabaseFixtures();

        /** @var FooEntity $result */
        $result = $this->persister->load(['id' => 1]);

        $this->assertInstanceOf(FooEntity::class, $result);

        $this->assertEquals(1, $result->getId());
        $this->assertEquals('foo1', $result->getName());
        $this->assertEquals(null, $result->getBar());
        $this->assertStringMatchesFormat('%d-%d-%d %d:%d:%d', $result->getCreatedTime());
    }

    public function testLoadNotFound(): void
    {
        $this->createDatabaseFixtures();

        $result = $this->persister->load(['id' => 42]);

        $this->assertNull($result);
    }

    private function setEntityWithId(object $entity, int $id): void
    {
        $reflection_prop = new ReflectionProperty(FooEntity::class, 'id');
        $reflection_prop->setAccessible(true);
        $reflection_prop->setValue($entity, $id);
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
