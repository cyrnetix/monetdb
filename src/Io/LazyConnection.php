<?php

declare(strict_types=1);

namespace Cyrnetix\MonetDB\Io;

use Exception;
use Evenement\EventEmitter;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use Cyrnetix\MonetDB\ConnectionInterface;
use Cyrnetix\MonetDB\Factory;
use Cyrnetix\MonetDB\QueryResult;
use React\Promise\PromiseInterface;
use React\Stream\ReadableStreamInterface;
use RuntimeException;

use function React\Promise\reject;
use function React\Promise\resolve;
use function React\Promise\Stream\unwrapReadable;

/**
 * Class LazyConnection
 *
 * @package Cyrnetix\MonetDB\Io
 */
class LazyConnection extends EventEmitter implements ConnectionInterface
{
    /**
     * Connection factory
     *
     * @var \Cyrnetix\MonetDB\Factory
     */
    private $factory;

    /**
     * Connection string
     *
     * @var string
     */
    private $uri;

    /**
     * Connecting socket
     *
     * @var \React\Promise\PromiseInterface
     */
    private $connecting;

    /**
     * Event loop
     *
     * @var \React\EventLoop\LoopInterface
     */
    private $loop;

    /**
     * Idle period, default 60 seconds
     *
     * @var float
     */
    private $idlePeriod = 60.0;

    /**
     * Disconnecting socket
     *
     * @var \React\Promise\PromiseInterface
     */
    private $disconnecting;

    /**
     * Connection closed state
     *
     * @var bool
     */
    private $closed = false;

    /**
     * Idle timer
     *
     * @var \React\EventLoop\TimerInterface
     */
    private $idleTimer;

    /**
     * Number of pending connections
     *
     * @var int
     */
    private $pending = 0;

    /**
     * LazyConnection constructor.
     *
     * @param \Cyrnetix\MonetDB\Factory $factory
     * @param string $uri
     * @param \React\EventLoop\LoopInterface $loop
     */
    public function __construct(Factory $factory, string $uri, LoopInterface $loop)
    {
        $args = [];
        \parse_str(\parse_url($uri, \PHP_URL_QUERY) ?? '', $args);
        if (true === isset($args['idle'])) {
            $this->idlePeriod = (float)$args['idle'];
        }

        $this->factory = $factory;
        $this->uri = $uri;
        $this->loop = $loop;
    }

    /**
     * Connecting
     *
     * @return PromiseInterface|null
     */
    private function connecting(): ?PromiseInterface
    {
        if ($this->connecting !== null) {
            return $this->connecting;
        }

        // Force-close connection if still waiting for previous disconnection
        if ($this->disconnecting !== null) {
            // @todo wth?
            //$this->disconnecting->close();
            $this->disconnecting->cancel();
            $this->disconnecting = null;
        }

        $this->connecting = $connecting = $this->factory->createConnection($this->uri);
        $this->connecting->then(function (ConnectionInterface $connection) {
            echo "Connection completed\n";
            // Connection completed => remember only until closed
            $connection->on('close', function () {
                $this->connecting = null;

                if ($this->idleTimer !== null) {
                    $this->loop->cancelTimer($this->idleTimer);
                    $this->idleTimer = null;
                }
            });
        }, function () {
            echo "Connection failed\n";
            // Connection failed => discard connection attempt
            $this->connecting = null;
        });

        return $connecting;
    }

    /**
     * @return void
     */
    private function awake(): void
    {
        echo "Awake!\n";
        ++$this->pending;

        if ($this->idleTimer !== null) {
            $this->loop->cancelTimer($this->idleTimer);
            $this->idleTimer = null;
        }
    }

    /**
     * @return void
     */
    private function idle(): void
    {
        echo "Idle\n";
        --$this->pending;

        if ($this->pending < 1 && $this->idlePeriod >= 0 &&  null !== $this->connecting) {
            $this->idleTimer = $this->loop->addTimer($this->idlePeriod, function () {
                $this->connecting->then(function (ConnectionInterface $connection) {
                    $this->disconnecting = $connection;
                    $connection->quit()->then(
                        function () {
                            echo "Successfully disconnected\n";
                            // Successfully disconnected => remove reference
                            $this->disconnecting = null;
                        },
                        function () use ($connection) {
                            echo "Soft close failed\n";
                            // Soft-close failed => force-close connection
                            $connection->close();
                            $this->disconnecting = null;
                        }
                    );
                });

                $this->connecting = null;
                $this->idleTimer = null;
            });
        }
    }

    /**
     * Run a query
     *
     * @param string $sql
     * @param array $params
     * @return \React\Promise\PromiseInterface<QueryResult, \RuntimeException>
     */
    public function query(string $sql, array $params = []): PromiseInterface
    {
        if (true === $this->closed) {
            return reject(new RuntimeException('Connection closed'));
        }

        return $this->connecting()->then(function (ConnectionInterface $connection) use ($sql, $params) {
            echo "Start query -----------------------------------------------------------------------\n";
            $this->awake();

            return $connection->query($sql, $params)->then(
                function (QueryResult $result) {
                    $this->idle();

                    return $result;
                },
                function (Exception $exception) {
                    $this->idle();

                    throw $exception;
                }
            );
        });
    }

    /**
     * Stream query result
     *
     * @param string $sql
     * @param array $params
     * @return \React\Stream\ReadableStreamInterface
     * @throws Exception
     */
    public function queryStream(string $sql, array $params = []): ReadableStreamInterface
    {
        if ($this->closed) {
            throw new RuntimeException('Connection closed');
        }

        return unwrapReadable(
            $this->connecting()->then(function (ConnectionInterface $connection) use ($sql, $params) {
                $stream = $connection->queryStream($sql, $params);

                $this->awake();

                $stream->on('close', function () {
                    $this->idle();
                });

                return $stream;
            })
        );
    }

    /**
     * Ping the server
     *
     * @return \React\Promise\PromiseInterface
     */
    public function ping(): PromiseInterface
    {
        if (true === $this->closed) {
            return reject(new Exception('Connection closed'));
        }

        return $this->connecting()->then(function (ConnectionInterface $connection) {
            $this->awake();
            return $connection->ping()->then(
                function () {
                    $this->idle();
                },
                function (Exception $exception) {
                    $this->idle();
                    throw $exception;
                }
            );
        });
    }

    /**
     * @return \React\Promise\PromiseInterface
     */
    public function quit(): PromiseInterface
    {
        if (true === $this->closed) {
            return reject(new Exception('Connection closed'));
        }

        // Not already connecting => no need to connect, simply close virtual connection
        if (null  ===  $this->connecting) {
            $this->close();
            return resolve();
        }

        return $this->connecting()->then(function (ConnectionInterface $connection) {
            $this->awake();

            return $connection->quit()->then(
                function (): void {
                    $this->close();
                },
                function (Exception $exception) {
                    $this->close();
                    throw $exception;
                }
            );
        });
    }

    /**
     * Close the connection
     *
     * @return void
     */
    public function close(): void
    {
        if (true === $this->closed) {
            return;
        }

        $this->closed = true;

        // Force-close connection if still waiting for previous disconnection
        if ($this->disconnecting !== null) {
            $this->disconnecting->close();
            $this->disconnecting = null;
        }

        // Either close active connection or cancel pending connection attempt
        if ($this->connecting !== null) {
            $this->connecting->then(function (ConnectionInterface $connection) {
                $connection->close();
            });

            $this->connecting->cancel();
            $this->connecting = null;
        }

        if ($this->idleTimer !== null) {
            $this->loop->cancelTimer($this->idleTimer);
            $this->idleTimer = null;
        }

        $this->emit('close');
        $this->removeAllListeners();
    }
}
