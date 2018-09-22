<?php declare(strict_types=1);
namespace Kekos\PhpSnake\Tests;

use PDO;
use Kekos\PhpSnake\Connection;
use PHPUnit\Framework\TestCase;

class ConnectionTest extends TestCase
{
    public function testCreatesPdo()
    {
        $connection = new Connection(
            $GLOBALS['DB_DSN'],
            $GLOBALS['DB_USERNAME'],
            $GLOBALS['DB_PASSWORD']
        );

        $this->assertAttributeInstanceOf(PDO::class, 'pdo', $connection);
    }
}
