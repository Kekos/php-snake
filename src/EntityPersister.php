<?php declare(strict_types=1);
/**
 * PHP Snake
 *
 * EntityPersister class
 */

namespace Kekos\PhpSnake;

use PDO;
use QueryBuilder\QueryBuilder;
use QueryBuilder\QueryBuilders\Raw;
use QueryBuilder\QueryBuilders\Select;

final class EntityPersister
{
    /** @var EntityMeta */
    private $meta;
    /** @var Connection */
    private $conn;
    /** @var object[] */
    private $queued_inserts = [];

    public function __construct(EntityMeta $meta, Connection $conn)
    {
        $this->meta = $meta;
        $this->conn = $conn;
    }

    public function addInsert(object $entity): void
    {
        $this->queued_inserts[spl_object_id($entity)] = $entity;
    }

    public function executeInserts(): array
    {
        if (empty($this->queued_inserts)) {
            return [];
        }

        $insert_sql = $this->getInsertSql();
        $primary_columns = $this->meta->getPrimaryKeyColumns();
        $stmt = $this->conn->prepare($insert_sql);
        $has_auto_increment = $this->meta->hasAutoIncrementPrimary();
        $genereated_ids = [];

        foreach ($this->queued_inserts as $entity) {
            $values = $this->meta->getColumnsWithValues($entity);
            $values = array_values(array_diff_key($values, $primary_columns));

            $stmt->execute($values);

            if ($has_auto_increment) {
                $genereated_ids[spl_object_id($entity)] = $this->conn->getPdo()->lastInsertId();
            }
        }

        $stmt->closeCursor();
        $this->queued_inserts = [];

        return $genereated_ids;
    }

    public function update(object $entity): void
    {
        $update_sql = $this->getUpdateSql($entity);

        $stmt = $this->conn->prepare((string) $update_sql);
        $stmt->execute($update_sql->getParams());
        $stmt->closeCursor();
    }

    public function delete(object $entity): void
    {
        $delete_sql = $this->getDeleteSql($entity);

        $stmt = $this->conn->prepare((string) $delete_sql);
        $stmt->execute($delete_sql->getParams());
        $stmt->closeCursor();
    }

    public function exists(array $criteria): bool
    {
        $qb = $this->getSelectQueryBuilder();
        $qb->columns(new Raw('1'));

        foreach ($criteria as $column => $value) {
            $qb->where($column, '=', $value);
        }

        $sql = $qb->toSql();

        $stmt = $this->conn->prepare((string) $sql);
        $stmt->execute($sql->getParams());

        return (bool) $stmt->fetchColumn();
    }

    public function load(array $criteria): ?object
    {
        $qb = $this->getSelectQueryBuilder();

        foreach ($criteria as $column => $value) {
            $qb->where($column, '=', $value);
        }

        $sql = $qb->toSql();

        $stmt = $this->conn->prepare((string) $sql);
        $stmt->execute($sql->getParams());

        $result = $stmt->fetchObject($this->meta->getClassName());

        if (!is_object($result) && $result !== null) {
            return null;
        }

        return $result;
    }

    public function loadAll(callable $builder_func = null): array
    {
        $qb = $this->getSelectQueryBuilder();

        if (is_callable($builder_func)) {
            $builder_func($qb);
        }

        $sql = $qb->toSql();

        $stmt = $this->conn->prepare((string) $sql);
        $stmt->execute($sql->getParams());

        $result = $stmt->fetchAll(PDO::FETCH_CLASS, $this->meta->getClassName());

        if (!is_array($result)) {
            return [];
        }

        return $result;
    }

    public function getInsertSql(): string
    {
        $table_name = $this->meta->getTableName();
        $qb = QueryBuilder::insert($table_name);
        $primary_columns = $this->meta->getPrimaryKeyColumns();
        $values = [];

        foreach ($this->meta->getDefaultColumns() as $column) {
            if (isset($primary_columns[$column])) {
                continue;
            }

            $values[$column] = null;
        }

        $qb->values($values);

        return (string) $qb->toSql();
    }

    public function getUpdateSql(object $entity): Raw
    {
        $table_name = $this->meta->getTableName();
        $qb = QueryBuilder::update($table_name);
        $primary_columns = $this->meta->getPrimaryKeyColumns();
        $values = [];
        $primary_values = [];

        foreach ($this->meta->getColumnsWithValues($entity) as $column => $value) {
            if (isset($primary_columns[$column])) {
                $primary_values[$column] = $value;
            } else {
                $values[$column] = $value;
            }
        }

        $qb->set($values);

        foreach ($primary_values as $column => $value) {
            $qb->where($column, '=', $value);
        }

        return $qb->toSql();
    }

    public function getDeleteSql(object $entity): Raw
    {
        $table_name = $this->meta->getTableName();
        $qb = QueryBuilder::delete($table_name);
        $primary_columns = $this->meta->getPrimaryKeyColumns();
        $primary_values = [];

        foreach ($this->meta->getColumnsWithValues($entity) as $column => $value) {
            if (!isset($primary_columns[$column])) {
                continue;
            }

            $primary_values[$column] = $value;
        }

        foreach ($primary_values as $column => $value) {
            $qb->where($column, '=', $value);
        }

        return $qb->toSql();
    }

    public function getSelectQueryBuilder(): Select
    {
        $table_name = $this->meta->getTableName();

        return QueryBuilder::select($table_name);
    }
}
