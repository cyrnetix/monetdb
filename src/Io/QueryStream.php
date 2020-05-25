<?php

declare(strict_types=1);

namespace Cyrnetix\MonetDB\Io;

use Cyrnetix\MonetDB\Type\Row;
use Evenement\EventEmitter;
use Cyrnetix\Monetdb\Command\QueryCommand;
use React\Socket\ConnectionInterface;
use React\Stream\ReadableStreamInterface;
use React\Stream\Util;
use React\Stream\WritableStreamInterface;

/**
 * @internal
 * @see Connection::queryStream()
 */
class QueryStream extends EventEmitter implements ReadableStreamInterface
{
    /**
     * @var ConnectionInterface
     */
    private $connection;

    /**
     * @var bool
     */
    private $started = false;

    /**
     * @var bool
     */
    private $closed = false;

    /**
     * @var bool
     */
    private $paused = false;

    /**
     * QueryStream constructor.
     * @param QueryCommand $command
     * @param ConnectionInterface $connection
     */
    public function __construct(QueryCommand $command, ConnectionInterface $connection)
    {
        $this->command = $command;
        $this->connection = $connection;

        // Forward result set rows until result set end
        $command->on('result', function (array $row): void {
            if (false === $this->started && true === $this->paused) {
                $this->connection->pause();
            }

            $this->started = true;

            $this->emit('data', [$row]);
        });

        $command->on('end', function () {
            $this->emit('end');
            $this->close();
        });

        // Status reply (response without result set) ends stream without data
        $command->on('success', function () {
            $this->emit('end');
            $this->close();
        });

        $command->on('error', function ($err) {
            $this->emit('error', array($err));
            $this->close();
        });
    }

    /**
     * Check if readable
     *
     * @return bool
     */
    public function isReadable(): bool
    {
        return false === $this->closed;
    }

    /**
     * Pause the stream
     *
     * return
     */
    public function pause(): void
    {
        $this->paused = true;
        if (true === $this->started && false === $this->closed) {
            $this->connection->pause();
        }
    }

    /**
     * Resume the stream
     *
     * @return void
     */
    public function resume(): void
    {
        $this->paused = false;
        if (true === $this->started && false === $this->closed) {
            $this->connection->resume();
        }
    }

    /**
     * Close the stream
     *
     * @return void
     */
    public function close(): void
    {
        if (true === $this->closed) {
            return;
        }

        $this->closed = true;
        if (true === $this->started && true === $this->paused) {
            $this->connection->resume();
        }

        $this->emit('close');
        $this->removeAllListeners();
    }

    /**
     * Pipe to another stream
     *
     * @param WritableStreamInterface $dest
     * @param array $options
     * @return WritableStreamInterface
     */
    public function pipe(WritableStreamInterface $dest, array $options = []): WritableStreamInterface
    {
        return Util::pipe($this, $dest, $options);
    }
}
