<?php

declare(strict_types=1);

namespace Cyrnetix\MonetDB\Io;

use Evenement\EventEmitter;
use Cyrnetix\MonetDB\Command\CommandInterface;
use SplQueue;

/**
 * Command executor
 *
 * @package Cyrnetix\MonetDB\Io
 */
class Executor extends EventEmitter
{
    /**
     * Queued commands
     *
     * @var \SplQueue<CommandInterface>
     */
    public $queue;

    /**
     * Executor constructor.
     */
    public function __construct()
    {
        $this->queue = new SplQueue();
    }

    /**
     * Get the idle state
     *
     * @return bool
     */
    public function isIdle(): bool
    {
        return $this->queue->isEmpty();
    }

    /**
     * Queue a single command
     *
     * @param CommandInterface $command
     * @return CommandInterface
     */
    public function enqueue(CommandInterface $command): CommandInterface
    {
        $this->queue->enqueue($command);
        $this->emit('new');

        return $command;
    }

    /**
     * Pick a command from the queue to execute
     *
     * @return mixed
     */
    public function dequeue()
    {
        return $this->queue->dequeue();
    }
}
