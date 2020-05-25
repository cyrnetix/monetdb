<?php

declare(strict_types=1);

namespace Cyrnetix\MonetDB\Io;

/**
 * Class Query
 *
 * @package Cyrnetix\MonetDB\Io
 */
class Query
{
    /**
     * Query string
     *
     * @var string
     */
    private $sql;

    /**
     * Bound parameters
     *
     * @var array
     */
    private $params = [];

    /**
     * Query constructor.
     *
     * @param string $sql
     */
    public function __construct(string $sql)
    {
        $this->sql = $sql;
    }

    /**
     * Get the sql statement
     *
     * @return string
     */
    public function getSql(): string
    {
        return $this->sql;
    }

    /**
     * Bind params
     *
     * @param array $params
     * @return void
     */
    public function bindParamsFromArray(array $params): void
    {
    }

    public function __toString()
    {
        return $this->sql;
    }
}
