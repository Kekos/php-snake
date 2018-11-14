<?php declare(strict_types=1);
/**
 * PHP Snake
 *
 * EntityMeta class
 */

namespace Kekos\PhpSnake;

use ReflectionClass;
use ReflectionException;
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

    public function getClassName(): string
    {
        return $this->class_name;
    }

    public function getTableName(): string
    {
        return $this->table_name;
    }

    /**
     * @return array<string,bool>
     * @throws SnakeMetaException
     */
    public function getPrimaryKeyColumns(): array
    {
        try {
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
        } catch (ReflectionException $ex) {
        }

        return ['id' => true];
    }

    public function hasAutoIncrementPrimary(): bool
    {
        foreach ($this->getPrimaryKeyColumns() as $column => $is_auto_increment) {
            if ($is_auto_increment) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return ReflectionProperty[]
     * @throws ReflectionException
     */
    public function getColumnReflections(): array
    {
        $reflection = new ReflectionClass($this->class_name);

        return array_filter($reflection->getProperties(), function (ReflectionProperty $property): bool {
            return ($property->isDefault() && !$property->isStatic());
        });
    }

    public function getDefaultColumns(): array
    {
        $properties = [];

        foreach ($this->getColumnReflections() as $property) {
            $properties[] = $property->getName();
        }

        return $properties;
    }

    /**
     * @param object $entity_instance
     * @return array<string,mixed>
     * @throws ReflectionException
     */
    public function getColumnsWithValues(object $entity_instance): array
    {
        $properties = [];

        foreach ($this->getColumnReflections() as $property) {
            $property->setAccessible(true);
            $name = $property->getName();
            $value = $property->getValue($entity_instance);

            $properties[$name] = $value;
        }

        return $properties;
    }

    /**
     * @param object $entity_instance
     * @return array<string,mixed>
     * @throws ReflectionException
     * @throws SnakeMetaException
     */
    public function getPrimaryColumnsWithValues(object $entity_instance): array
    {
        $values = array_intersect_key(
            $this->getColumnsWithValues($entity_instance),
            $this->getPrimaryKeyColumns()
        );

        $values = array_filter($values, function ($value): bool {
            return ($value !== null);
        });

        return $values;
    }

    /**
     * @param string $column
     * @param object[] $entities
     * @param array $values
     * @throws ReflectionException
     * @throws SnakeMetaException
     */
    public function setColumnWithValue(string $column, array $entities, array $values): void
    {
        $property = new ReflectionProperty($this->class_name, $column);
        $property->setAccessible(true);

        foreach ($entities as $index => $entity) {
            if (get_class($entity) !== $this->class_name) {
                throw new SnakeMetaException(sprintf(
                    'Given entity "%s" is not instance of "%s" when setting column "%s"',
                    get_class($entity),
                    $this->class_name,
                    $column
                ));
            }

            $property->setValue($entity, $values[$index]);
        }
    }

    private static function convertClassToTableName(string $class_name): string
    {
        $ns_pos = strrpos($class_name, '\\');
        $table_name = substr($class_name, $ns_pos + 1);
        $snake_replace = preg_replace('/[A-Z]/', '_\\0', lcfirst($table_name));

        if ($snake_replace === null) {
            throw new SnakeMetaException(sprintf(
                'Regex error in EntityMeta::convertClassToTableName for class name "%s"',
                $class_name
            ));
        }

        return strtolower($snake_replace);
    }
}
