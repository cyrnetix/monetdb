<?php

declare(strict_types=1);

namespace Cyrnetix\MonetDB;

/**
 * Result of our query
 *
 * @packege Cyrnetix\MonetDB
 */
class QueryResult
{
    /**
     * The last insert id of the current session
     *
     * @var int|null
     */
    public $insertId;

    /**
     * The number of affected rows
     *
     * @var int|null
     */
    public $affectedRows;

    /**
     * Result fields
     *
     * @var array|null
     */
    public $resultFields;

    /**
     * Result rows
     *
     * @var array|null
     */
    public $resultRows;

    /**
     * Number of warnings
     *
     * @var int|null
     */
    public $warningCount;
}
