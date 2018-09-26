<?php declare(strict_types=1);
namespace Kekos\PhpSnake\Tests\Fixtures;

class FooEntity
{
    /** @var int */
    private $id;
    /** @var string */
    private $name;
    /** @var string|null */
    private $bar;
    /** @var string */
    private $created_time;

    // This property should be ignored as it's static
    private static $decoy;

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return FooEntity
     */
    public function setName(string $name): FooEntity
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getBar(): ?string
    {
        return $this->bar;
    }

    /**
     * @param null|string $bar
     * @return FooEntity
     */
    public function setBar(?string $bar): FooEntity
    {
        $this->bar = $bar;
        return $this;
    }

    /**
     * @return string
     */
    public function getCreatedTime(): string
    {
        return $this->created_time;
    }

    /**
     * @param string $created_time
     * @return FooEntity
     */
    public function setCreatedTime(string $created_time): FooEntity
    {
        $this->created_time = $created_time;
        return $this;
    }
}
