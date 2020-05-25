<?php

declare(strict_types=1);

namespace Cyrnetix\MonetDB\Command;

/**
 * Class XAutoCommitCommand
 *
 * @package Cyrnetix\MonetDB\Command
 */
class XAutoCommitCommand extends AbstractCommand
{
    /**
     * Auto commit true or false flag
     *
     * @var bool
     */
    private $autoCommit;

    /**
     * XAutoCommitCommand constructor.
     *
     * @param bool $autoCommit
     */
    public function __construct(bool $autoCommit)
    {
        $this->autoCommit = $autoCommit;
    }

    /**
     * Get sql statement
     *
     * @return string
     */
    public function getSql(): string
    {
        return 'auto_commit ' . (true === $this->autoCommit ? 1 : 0);
    }

    /**
     * Get the command Id
     *
     * @return string
     */
    public function getId(): string
    {
        return 'X';
    }
}
