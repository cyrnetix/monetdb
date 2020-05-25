<?php

declare(strict_types=1);

namespace Cyrnetix\MonetDB\Type;

/**
 * Result row
 *
 * @package Cyrnetix\MonetDB\Type
 */
class Row
{
    /**
     * Returned fields
     *
     * @var array
     */
    public $fields;

    /**
     * Constructor
     *
     * @param array $fields
     */
    public function __construct(array $fields)
    {
        $this->fields = $fields;
    }
}
