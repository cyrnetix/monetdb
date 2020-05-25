<?php

declare(strict_types=1);

namespace Cyrnetix\MonetDB\Command;

/**
 * Class QuitCommand
 *
 * @package Cyrnetix\MonetDB\Command
 */
class QuitCommand extends AbstractCommand
{
    /**
     * Get the quit command id
     *
     * @return string
     */
    public function getId(): string
    {
        return 'X';
    }

    /**
     * Get the sql statement
     *
     * @return string
     */
    public function getSql(): string
    {
        return 'quit';
    }
}
