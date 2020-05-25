<?php

declare(strict_types=1);

namespace Cyrnetix\MonetDB;

use Evenement\EventEmitterInterface;
use React\Promise\PromiseInterface;
use React\Stream\ReadableStreamInterface;

/**
 * MonetDB connection interface
 *
 * @package Cyrnetix\MonetDB
 */
interface ConnectionInterface extends EventEmitterInterface
{
    /**
     * Execute a query and return the result
     *
     * @return \React\Promise\Promise<
     */
    public function query(string $sql, array $params = []): PromiseInterface;

    /**
     * Stream the response of a query
     *
     * @param string $sql
     * @param array $params
     * @return \React\Stream\ReadableStreamInterface
     */
    public function queryStream(string $sql, array $params = []): ReadableStreamInterface;

    /**
     * Send a ping to the server
     *
     * @return \React\Promise\PromiseInterface
     */
    public function ping(): PromiseInterface;

    /**
     * Quit gracefully
     *
     * @return \React\Promise\PromiseInterface
     */
    public function quit(): PromiseInterface;

    /**
     * Close the connection
     *
     * @return void
     */
    public function close(): void;
}
