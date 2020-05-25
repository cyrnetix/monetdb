<?php

declare(strict_types=1);

namespace Cyrnetix\MonetDB\Command;

/**
 * Interface CommandInterface
 *
 * @package Cyrnetix\MonetDB\Command
 */
interface CommandInterface
{
    /**
     * Get the command id
     *
     * @return string
     */
    public function getId(): string;
}
