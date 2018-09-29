<?php declare(strict_types=1);
/**
 * PHP Snake
 *
 * EntityMeta class
 */

namespace Kekos\PhpSnake;

use ReflectionClass;
use ReflectionProperty;
use Kekos\PhpSnake\Exception\SnakeMetaException;

final class EntityMeta
{
    /** @var string */
    private $class_name;
    /** @var string */
    private $table_name;

    public function __construct(string $class_name)
    {
        $this->class_name = $class_name;
        $this->table_name = self::convertClassToTableName($class_name);
    }

    public function getTableName(): string
    {
        return $this->table_name;
    }

    /**
     * @return string[]
     * @throws SnakeMetaException
     * @throws \ReflectionException
     */
    public function getPrimaryKeyColumns(): array
    {
        if (property_exists($this->class_name, 'primary_definition')) {
            $reflection_property = new ReflectionProperty($this->class_name, 'primary_definition');
            $reflection_property->setAccessible(true);

            if (!$reflection_property->isStatic()) {
                throw new SnakeMetaException(sprintf(
                    'Error in definition of the primary definition of %s entity. Property must be static!',
                    $this->class_name
                ));
            }

            $primary_definition = $reflection_property->getValue();

            if (!is_array($primary_definition)) {
                throw new SnakeMetaException(sprintf(
                    'Error in definition of the primary definition of %s entity. Property was of type %s, expected array',
                    $this->class_name,
                    gettype($primary_definition)
                ));
            }

            return $primary_definition;
        }

        return ['id'];
    }

    public function getColumnsWithValues(object $entity_instance): array
    {
        $properties = [];

        $reflection = new ReflectionClass($this->class_name);
        foreach ($reflection->getProperties() as $property) {
            if (!$property->isDefault() || $property->isStatic()) {
                continue;
            }

            $property->setAccessible(true);
            $name = $property->getName();
            $value = $property->getValue($entity_instance);

            $properties[$name] = $value;
        }

        return $properties;
    }

    private static function convertClassToTableName(string $class_name): string
    {
        $ns_pos = strrpos($class_name, '\\');
        $table_name = substr($class_name, $ns_pos + 1);

        return strtolower(preg_replace('/[A-Z]/', '_\\0', lcfirst($table_name)));
    }
}