<?php declare(strict_types=1);
/**
 * PHP Snake
 *
 * Connection class
 */

namespace Kekos\PhpSnake;

use PDO;

class Connection
{
    /** @var PDO */
    private $pdo;

    public function __construct(string $dsn, string $username, string $password)
    {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $this->pdo = new PDO($dsn, $username, $password, $options);
    }
}
