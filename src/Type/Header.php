<?php

declare(strict_types=1);

namespace Cyrnetix\MonetDB\Type;

/**
 * Response header
 */
class Header
{
    /**
     * ID
     *
     * @var int
     */
    public $id;

    /**
     * Number of rows
     *
     * @var int
     */
    public $rows;

    /**
     * Number of columns
     *
     * @var int
     */
    public $cols;

    /**
     * Index number
     *
     * @var int
     */
    public $index;

    /**
     * Constructor
     *
     * @param int $id
     * @param int $rows
     * @param int $cols
     * @param int $index
     */
    public function __construct(int $id, int $rows, int $cols, int $index)
    {
        $this->id = $id;
        $this->rows = $rows;
        $this->cols = $cols;
        $this->index = $index;
    }
}
