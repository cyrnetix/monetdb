<?php

declare(strict_types=1);

namespace Cyrnetix\MonetDB\Io;

use Cyrnetix\MonetDB\Command\XAutoCommitCommand;
use Evenement\EventEmitter;
use Exception;
use Cyrnetix\MonetDB\Command\CommandInterface;
use Cyrnetix\MonetDB\Command\QueryCommand;
use Cyrnetix\MonetDB\Command\QuitCommand;
use Cyrnetix\MonetDB\ConnectionInterface;
use Cyrnetix\MonetDB\Exception\ConnectionException;
use Cyrnetix\MonetDB\QueryResult;
use React\Promise\Deferred;
use React\Promise\Promise;
use React\Promise\PromiseInterface;
use React\Stream\ReadableStreamInterface;
use RuntimeException;

use function React\Promise\reject;

use React\Socket\ConnectionInterface as SocketConnectionInterface;

/**
 * Connection
 *
 * @package Cyrnetix\MonetDB\Io
 */
class Connection extends EventEmitter implements ConnectionInterface
{
    /**
     * States
     *
     * @const int
     */
    private const STATE_AUTHENTICATED = 5;
    private const STATE_CLOSING       = 6;
    private const STATE_CLOSED        = 7;

    /**
     * Current connection stream
     *
     * @var \React\Socket\ConnectionInterface
     */
    private $stream;

    /**
     * Command executor
     *
     * @var Executor
     */
    private $executor;

    /**
     * Current state
     *
     * @var int
     */
    private $state = self::STATE_AUTHENTICATED;

    /**
     * Connection constructor.
     *
     * @param SocketConnectionInterface $stream
     * @param Executor $executor
     */
    public function __construct(SocketConnectionInterface $stream, Executor $executor)
    {
        $this->stream   = $stream;
        $this->executor = $executor;

        $stream->on('error', [$this, 'handleConnectionError']);
        $stream->on('close', [$this, 'handleConnectionClosed']);
    }

    /**
     * Error from socket.
     *
     * @param Exception $err
     * @return void
     * @internal
     */
    public function handleConnectionError(Exception $err): void
    {
        $this->emit('error', [$err, $this]);
    }

    /**
     * Handle connection closed
     *
     * @return void
     * @internal
     */
    public function handleConnectionClosed(): void
    {
        if ($this->state < self::STATE_CLOSING) {
            $this->emit('error', [new ConnectionException('Monetdb server has gone away'), $this]);
        }

        $this->close();
    }

    /**
     * Auto commit inserts, updates and deletes
     *
     * @param bool $autoCommit
     * @return PromiseInterface
     */
    public function setAutoCommit(bool $autoCommit): PromiseInterface
    {
        $command = new XAutoCommitCommand($autoCommit);

        $this->executeCommand($command);

        $deferred = new Deferred();

        // On success of command
        $command->on('success', function () use ($command, $deferred, $autoCommit) {
            $deferred->resolve($autoCommit);
        });

        return $deferred->promise();
    }

    /**
     * Execute a query
     *
     * @param string $sql
     * @param array $params
     * @return \React\Promise\PromiseInterface
     */
    public function query(string $sql, array $params = []): PromiseInterface
    {
        $query = new Query($sql);
        if (false === empty($params)) {
            $query->bindParamsFromArray($params);
        }

        $command = new QueryCommand();
        $command->setQuery($query);
        try {
            $this->executeCommand($command);
        } catch (RuntimeException $e) {
            return reject($e);
        }

        $deferred = new Deferred();

        // Store all result set rows until result set end
        $rows = [];

        // Received a result
        $command->on('result', function ($row) use (&$rows) {
            echo 'result event fired' . PHP_EOL;
            $rows[] = $row;
        });

        // Received the end of the request
        $command->on('end', function () use ($command, $deferred, &$rows) {
            echo 'end event fired' . PHP_EOL;

            $result = new QueryResult();
            $result->resultFields = $command->resultFields;
            $result->resultRows = $rows;
            $result->warningCount = $command->warningCount;

            $rows = [];

            $deferred->resolve($result);
        });

        // Resolve / reject status reply (response without result set)
        $command->on('error', function ($error) use ($deferred) {
            $deferred->reject($error);
        });

        // On success of insert, update or delete
        $command->on('success', function () use ($command, $deferred) {
            $result = new QueryResult();
            $result->affectedRows = $command->affectedRows;
            $result->insertId = $command->insertId;
            $result->warningCount = $command->warningCount;

            $deferred->resolve($result);
        });

        return $deferred->promise();
    }

    /**
     * Stream a query result
     *
     * @param string $sql
     * @param array $params
     * @return ReadableStreamInterface
     */
    public function queryStream(string $sql, array $params = []): ReadableStreamInterface
    {
        $query = new Query($sql);
        if (false === empty($params)) {
            $query->bindParamsFromArray($params);
        }

        $command = new QueryCommand();
        $command->setQuery($query);
        $this->executeCommand($command);

        return new QueryStream($command, $this->stream);
    }

    /**
     * Ping the server / keep alive
     *
     * @return \React\Promise\PromiseInterface
     */
    public function ping(): PromiseInterface
    {
        // TODO: Implement ping() method.
    }

    /**
     * Quit the client
     *
     * @return PromiseInterface
     */
    public function quit(): PromiseInterface
    {
        return new Promise(function ($resolve, $reject) {
            $this->executeCommand(new QuitCommand())
                ->on('error', function ($reason) use ($reject) {
                    $reject($reason);
                })
                ->on('success', function () use ($resolve) {
                    $this->state = self::STATE_CLOSED;
                    $this->emit('end', [$this]);
                    $this->emit('close', [$this]);
                    $resolve();
                });
            $this->state = self::STATE_CLOSING;
        });
    }

    /**
     * Close the connection
     *
     * @return void
     */
    public function close(): void
    {
        if ($this->state === self::STATE_CLOSED) {
            return;
        }

        $this->state = self::STATE_CLOSED;
        $this->stream->close();

        // reject all pending commands if connection is closed
        while (false === $this->executor->isIdle()) {
            $command = $this->executor->dequeue();
            $command->emit('error', [
                new ConnectionException('Connection lost')
            ]);
        }

        $this->emit('close');
        $this->removeAllListeners();
    }

    /**
     * @param CommandInterface $command
     * @return mixed
     */
    private function executeCommand(CommandInterface $command): CommandInterface
    {
        if (self::STATE_AUTHENTICATED === $this->state) {
            return $this->executor->enqueue($command);
        } else {
            throw new RuntimeException("Can't send command");
        }
    }
}
