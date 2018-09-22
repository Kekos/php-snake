<?php declare(strict_types=1);
/**
 * PHP Snake
 *
 * Connection class
 */

namespace Kekos\PhpSnake;

use PDO;
use Kekos\PhpSnake\Exception\SnakeException;

final class Connection
{
    /** @var PDO */
    private $pdo;

    /**
     * @param string $dsn
     * @param string $username
     * @param string $password
     * @throws SnakeException
     */
    public function __construct(string $dsn, string $username, string $password)
    {
        if (!self::hasDsnCharsetProperty($dsn)) {
            throw new SnakeException('Missing charset in DSN argument');
        }

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $this->pdo = new PDO($dsn, $username, $password, $options);
    }

    private static function hasDsnCharsetProperty(string $dsn): bool
    {
        list(, $properties) = explode(':', $dsn);
        $properties = explode(';', $properties);

        foreach ($properties as $property) {
            $property = trim($property);
            if (empty($property)) {
                continue;
            }

            list($name, $value) = explode('=', $property);

            if ($name === 'charset' && !empty($value)) {
                return true;
            }
        }

        return false;
    }
}
