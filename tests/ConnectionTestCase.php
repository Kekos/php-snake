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

abstract class ConnectionTestCase extends TestCase
{
    public const ROW_FIXTURES = [
        ['foo1', null],
        ['foo2', 'bar2'],
        ['foo3', 'bar3'],
    ];

    /** @var Connection */
    protected $conn;

    public static function setUpBeforeClass(): void
    {
        QueryBuilder::setAdapter(new MySqlAdapter());
    }

    protected function setUp(): void
    {
        $this->conn = new Connection($_ENV['DB_DSN'], $_ENV['DB_USERNAME'], $_ENV['DB_PASSWORD']);

        $this->conn->exec("CREATE TEMPORARY TABLE `foo_entity` (
              `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `name` VARCHAR(64) NOT NULL,
              `bar` VARCHAR(64) NULL,
              `created_time` DATETIME NOT NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");

        $this->conn->exec("CREATE TEMPORARY TABLE `bar_entity` (
              `bar_id` INT UNSIGNED NOT NULL,
              `baz_id` INT UNSIGNED NOT NULL,
              `info` VARCHAR(64) NOT NULL,
              PRIMARY KEY (`bar_id`, `baz_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");
    }

    protected function tearDown(): void
    {
        $this->conn->exec("DROP TABLE `foo_entity`");
        $this->conn->exec("DROP TABLE `bar_entity`");
    }

    protected function createDatabaseFixtures(): void
    {
        $stmt = $this->conn->prepare("INSERT INTO `foo_entity` (`name`, `bar`, `created_time`) VALUES (?, ?, NOW())");

        foreach (self::ROW_FIXTURES as $fixture) {
            $stmt->execute($fixture);
        }
    }

    protected function getTableCount(): int
    {
        $stmt = $this->conn->query("SELECT COUNT(*) AS `cnt` FROM `foo_entity`");
        if (is_bool($stmt)) {
            throw new RuntimeException('Selecting count from foo_entity returned error');
        }

        return $stmt->fetchObject()->cnt;
    }

    protected static function getFilledEntity(): FooEntity
    {
        $entity = new FooEntity();
        $entity
            ->setName('name')
            ->setBar(null)
            ->setCreatedTime(date('Y-m-d'));

        return $entity;
    }

    protected function setEntityWithId(object $entity, int $id): void
    {
        $reflection_prop = new ReflectionProperty(FooEntity::class, 'id');
        $reflection_prop->setAccessible(true);
        $reflection_prop->setValue($entity, $id);
    }
}
