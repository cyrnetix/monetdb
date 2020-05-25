<?php

declare(strict_types=1);

namespace Cyrnetix\MonetDB\Io;

/**
 * Current query environment, should maybe changed to 'state'
 *
 * @package Cyrnetix\MonetDB\Io
 */
class Env
{
    /**
     * Number of expected rows
     *
     * @var int
     */
    public $rows;

    /**
     * Number of received rows
     *
     * @var int
     */
    public $rowsReceived = 0;

    /**
     * Response id
     *
     * @var int
     */
    public $id;

    /**
     * Index
     *
     * @var int
     */
    public $index;

    /**
     * Type
     *
     * @var int
     */
    public $type;

    /**
     * Cols
     *
     * @var int
     */
    public $cols;

    /**
     * Tables
     *
     * @var string
     */
    public $tables = [];

    /**
     * Names
     *
     * @var string
     */
    public $names = [];

    /**
     * Types
     *
     * @var string
     */
    public $types = [];

    /**
     * Lengths
     *
     * @var string
     */
    public $lengths = [];

    /**
     * Tuples
     *
     * @var string
     */
    public $tuples = [];

    /**
     * Message
     *
     * @var string
     */
    public $message;

    /**
     * Db types
     *
     * @var string
     */
    public $dbtypes = [];

    /**
     * Convert to array
     *
     * @return array
     */
    public function toArray(): array
    {
        $fields = [];

        foreach ($this->names as $idx => $field) {
            $fields[] = [
                'name'   => $this->names[$idx],
                'table'  => $this->tables[$idx],
                'type'   => $this->types[$idx],
                'length' => $this->lengths[$idx],
            ];
        }

        return $fields;
    }
}
