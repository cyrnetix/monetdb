<?php

declare(strict_types=1);

namespace Cyrnetix\MonetDB\Command;

use Cyrnetix\MonetDB\Io\Query;

/**
 * Class QueryCommand
 *
 * @package Cyrnetix\MonetDB\Command
 */
class QueryCommand extends AbstractCommand
{
    /**
     * The query
     *
     * @var \Cyrnetix\MonetDB\Io\Query
     */
    public $query;

    /**
     * The returned fields
     *
     * @var array
     */
    public $fields;

    /**
     * The last insert id
     *
     * @var int|null
     */
    public $insertId;

    /**
     * The number off affected rows
     *
     * @var int
     */
    public $affectedRows;

    /**
     * The count of warnings
     *
     * @var int
     */
    public $warningCount;

    /**
     * The returned fields
     *
     * @var array
     */
    public $resultFields;

    /**
     * Response message
     *
     * @var string
     */
    public $message;

    /**
     * Set the query to execute
     *
     * @param \Cyrnetix\MonetDB\Io\Query $query
     */
    public function setQuery(Query $query)
    {
        $this->query = $query;
    }

    /**
     * Get the command id / prefix
     *
     * @return string
     */
    public function getId(): string
    {
        return 's';
    }

    /**
     * Get the sql statement
     *
     * @return string
     */
    public function getSql(): string
    {
        $query = $this->query;

        if ($query instanceof Query) {
            return $query->getSql() . ';';
        }

        return $query . ';';
    }
}
