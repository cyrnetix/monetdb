<?php

declare(strict_types=1);

namespace Cyrnetix\MonetDB\Command;

/**
 * Class XReplySizeCommand
 *
 * @package Cyrnetix\MonetDB\Command
 */
class XReplySizeCommand extends AbstractCommand
{
    /**
     * The reply size in
     *
     * @var int
     */
    private $replySize;

    /**
     * XReplySizeCommand constructor.
     *
     * @param int $replySize
     */
    public function __construct(int $replySize)
    {
        $this->replySize = $replySize;
    }

    /**
     * Get the sql statement
     *
     * @return string
     */
    public function getSql(): string
    {
        return 'reply_size ' . $this->replySize;
    }

    /**
     * Get the id
     *
     * @return string
     */
    public function getId(): string
    {
        return 'X';
    }
}
