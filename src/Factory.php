<?php

namespace Cyrnetix\MonetDB;

use InvalidArgumentException;
use React\EventLoop\LoopInterface;
use Cyrnetix\MonetDB\Command\AuthenticateCommand;
use Cyrnetix\MonetDB\Io\Connection;
use Cyrnetix\MonetDB\Io\Executor;
use Cyrnetix\MonetDB\Io\LazyConnection;
use Cyrnetix\MonetDB\Io\Parser;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use React\Promise\Timer\TimeoutException;
use React\Socket\Connector;
use React\Socket\ConnectorInterface;
use React\Socket\ConnectionInterface as SocketConnectionInterface;
use RuntimeException;
use Throwable;

use function React\Promise\reject;
use function React\Promise\Timer\timeout;

class Factory
{
    /**
     * Default port
     *
     * @const int
     */
    private const DEFAULT_PORT = 50000;

    /**
     * Default user name
     *
     * @const string
     */
    private const DEFAULT_USER = 'monetdb';

    /**
     * @var LoopInterface
     */
    private $loop;

    /**
     * @var Connector|ConnectorInterface
     */
    private $connector;

    /**
     * Factory constructor.
     *
     * @param LoopInterface $loop
     * @param ConnectorInterface|null $connector
     */
    public function __construct(LoopInterface $loop, ConnectorInterface $connector = null)
    {
        if (null === $connector) {
            $connector = new Connector($loop);
        }

        $this->loop = $loop;
        $this->connector = $connector;
    }

    /**
     * Create the connection and authenticate
     *
     * @param string $uri
     * @return \React\Promise\PromiseInterface
     */
    public function createConnection(string $uri): PromiseInterface
    {
        $parts = \parse_url('monetdb://' . $uri);
        if (false === isset($parts['scheme'], $parts['host']) || $parts['scheme'] !== 'monetdb') {
            return reject(new InvalidArgumentException('Invalid connect uri given'));
        }

        $connecting = $this->connector->connect(
            $parts['host'] . ':' . (true === isset($parts['port']) ? $parts['port'] : self::DEFAULT_PORT)
        );

        $deferred = new Deferred(function ($_, $reject) use ($connecting) {
            // Connection cancelled, start with rejecting attempt, then clean up
            $reject(new RuntimeException('Connection to database server cancelled'));

            // Either close successful connection or cancel pending connection attempt
            $connecting->then(function (SocketConnectionInterface $connection) {
                $connection->close();
            });

            $connecting->cancel();
        });

        $connecting->then(function (SocketConnectionInterface $stream) use ($parts, $deferred) {
            $executor = new Executor();
            $parser = new Parser($stream, $executor);

            $connection = new Connection($stream, $executor);
            $command = $executor->enqueue(new AuthenticateCommand(
                true === isset($parts['user']) ? \rawurldecode($parts['user']) : self::DEFAULT_USER,
                true === isset($parts['pass']) ? \rawurldecode($parts['pass']) : '',
                true === isset($parts['path']) ? \rawurldecode(ltrim($parts['path'], '/')) : ''
            ));

            $parser->start();

            $command->on('success', function () use ($deferred, $connection) {
                $deferred->resolve($connection);
            });

            $command->on('error', function ($error) use ($deferred, $stream) {
                $deferred->reject($error);
                $stream->close();
            });
        }, function ($error) use ($deferred) {
            $deferred->reject(new RuntimeException('Unable to connect to database server', 0, $error));
        });

        $args = [];
        if (true === isset($parts['query'])) {
            \parse_str($parts['query'], $args);
        }

        // Use timeout from explicit ?timeout=x parameter or default to PHP's default_socket_timeout (60)
        $timeout = true === isset($args['timeout']) ? (float)$args['timeout'] : (float)ini_get('default_socket_timeout');
        if ($timeout < 0) {
            return $deferred->promise();
        }

        return timeout($deferred->promise(), $timeout, $this->loop)
            ->then(null, function (Throwable $exception) {
                if ($exception instanceof TimeoutException) {
                    throw new RuntimeException(
                        'Connection to database server timed out after ' . $exception->getTimeout() . ' seconds'
                    );
                }

                throw $exception;
            });
    }

    /**
     * Create a connections which will connect when required
     *
     * @param string $uri
     * @return LazyConnection
     */
    public function createLazyConnection(string $uri): LazyConnection
    {
        return new LazyConnection($this, $uri, $this->loop);
    }
}
