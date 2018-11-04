<?php declare(strict_types=1);
/**
 * PHP Snake
 *
 * EntityManager class
 */

namespace Kekos\PhpSnake;

use Kekos\PhpSnake\Exception\SnakeException;

final class EntityManager
{
    public const STATE_MANAGED = 1;
    public const STATE_NEW = 2;
    public const STATE_DETACHED = 3;
    public const STATE_REMOVED = 4;

    /** @var Connection */
    private $connection;
    /** @var object[][] */
    private $identity_map = [];
    /** @var array<int, array> */
    private $entity_identifiers = [];
    /** @var int[] */
    private $entity_states = [];
    /** @var EntityMeta[] */
    private $metadata_cache = [];
    /** @var EntityPersister[] */
    private $persisters = [];
    /** @var array<int, object> */
    private $entity_inserts = [];
    /** @var array<int, object> */
    private $entity_updates = [];
    /** @var array<int, object> */
    private $entity_deletions = [];

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @param string $entity_classname
     * @param int|string|array<string, mixed> $id
     * @return null|object
     * @throws Exception\SnakeMetaException
     * @throws SnakeException
     */
    public function find(string $entity_classname, $id): ?object
    {
        $meta = $this->getEntityMeta($entity_classname);
        $primary_columns = $meta->getPrimaryKeyColumns();

        if (!is_array($id)) {
            $id = ['id' => $id];
        }

        $sorted_id = [];

        foreach ($primary_columns as $column => $auto) {
            if (!isset($id[$column])) {
                throw new SnakeException(sprintf(
                    'Tried to load entity of type "%s" but primary key "%s" are missing',
                    $entity_classname,
                    $column
                ));
            }

            $sorted_id[$column] = $id[$column];
            unset($id[$column]);
        }

        if (!empty($id)) {
            throw new SnakeException(sprintf(
                'Tried to load entity of type "%s" but got unknown primary keys %s',
                $entity_classname,
                implode(', ', array_keys($id))
            ));
        }

        if (($entity = $this->getById($sorted_id, $entity_classname)) !== null) {
            return $entity;
        }

        $entity = $this->getEntityPersister($entity_classname)->load($sorted_id);

        if ($entity === null) {
            return null;
        }

        $this->registerManaged($entity, $sorted_id);

        return $entity;
    }

    public function persist(object $entity): void
    {
        $entity_state = $this->getEntityState($entity);

        switch ($entity_state) {
            case self::STATE_MANAGED:
                break;

            case self::STATE_NEW:
                // persist as new
                break;

            case self::STATE_REMOVED:
                $oid = spl_object_id($entity);
                // unset from entity deletions
                // add to identity map
                // set entity state to MANAGED
                break;

            case self::STATE_DETACHED:
                throw new SnakeException('Detached entity can not be persisted');
                break;

            default:
                throw new SnakeException(sprintf(
                    'Unexpected entity state "%s" for entity of type "%s"',
                    $entity_state,
                    get_class($entity)
                ));
        }
    }

    public function getEntityState(object $entity): ?int
    {
        $oid = spl_object_id($entity);

        if (isset($this->entity_states[$oid])) {
            return $this->entity_states[$oid];
        }

        // TODO: implementera $assume?

        $meta = $this->getEntityMeta(get_class($entity));
        $id = $meta->getPrimaryKeyColumns();

        if (empty($id)) {
            return self::STATE_NEW;
        }

        if ($this->getById($id, $meta->getClassName())) {
            return self::STATE_DETACHED;
        }

        if ($this->getEntityPersister($meta->getClassName())->exists($id)) {
            return self::STATE_DETACHED;
        }

        return self::STATE_NEW;
    }

    private function registerManaged(object $entity, array $id): void
    {
        $oid = spl_object_id($entity);

        $this->entity_states[$oid] = self::STATE_MANAGED;
        $this->entity_identifiers[$oid] = $id;

        $this->addToIdentityMap($entity);
    }

    public function getEntityMeta(string $class): EntityMeta
    {
        if (!isset($this->metadata_cache[$class])) {
            $this->metadata_cache[$class] = new EntityMeta($class);
        }

        return $this->metadata_cache[$class];
    }

    public function getEntityPersister(string $class): EntityPersister
    {
        if (!isset($this->persisters[$class])) {
            $this->persisters[$class] = new EntityPersister($this->getEntityMeta($class), $this->connection);
        }

        return $this->persisters[$class];
    }

    public function getById(array $id, string $class_name): ?object
    {
        $id_hash = implode(' ', $id);

        return ($this->identity_map[$class_name][$id_hash] ?? null);
    }

    private function addToIdentityMap(object $entity): void
    {
        $oid = spl_object_id($entity);
        $class_name = get_class($entity);

        $id = $this->entity_identifiers[$oid];
        $id_hash = implode(' ', $id);

        $this->identity_map[$class_name][$id_hash] = $entity;
    }
}
