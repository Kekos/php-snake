<?php declare(strict_types=1);
namespace Kekos\PhpSnake\Tests;

use PDO;
use Kekos\PhpSnake\Connection;
use Kekos\PhpSnake\Exception\SnakeException;
use PHPUnit\Framework\TestCase;

class ConnectionTest extends TestCase
{
    public function testCreatesPdo(): void
    {
        $connection = new Connection(
            $_ENV['DB_DSN'],
            $_ENV['DB_USERNAME'],
            $_ENV['DB_PASSWORD']
        );

        $this->assertAttributeInstanceOf(PDO::class, 'pdo', $connection);
    }

    public function testThrowsMissingCharset(): void
    {
        $this->expectException(SnakeException::class);
        $this->expectExceptionMessage('Missing charset in DSN argument');

        new Connection('mysql:', '', '');
    }
}
