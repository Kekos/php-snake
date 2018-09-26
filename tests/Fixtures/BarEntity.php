<?php declare(strict_types=1);
namespace Kekos\PhpSnake\Tests\Fixtures;

class BarEntity
{
    /** @var int */
    private $bar_id;
    /** @var int */
    private $baz_id;
    /** @var string */
    private $info;

    private static $primary_definition = [
        'bar_id',
        'baz_id',
    ];

    /**
     * @return int
     */
    public function getBarId(): int
    {
        return $this->bar_id;
    }

    /**
     * @param int $bar_id
     * @return FooEntity
     */
    public function setBarId(int $bar_id): FooEntity
    {
        $this->bar_id = $bar_id;
        return $this;
    }

    /**
     * @return int
     */
    public function getBazId(): int
    {
        return $this->baz_id;
    }

    /**
     * @param int $baz_id
     * @return FooEntity
     */
    public function setBazId(int $baz_id): FooEntity
    {
        $this->baz_id = $baz_id;
        return $this;
    }

    /**
     * @return string
     */
    public function getInfo(): string
    {
        return $this->info;
    }

    /**
     * @param string $info
     * @return FooEntity
     */
    public function setInfo(string $info): FooEntity
    {
        $this->info = $info;
        return $this;
    }
}
