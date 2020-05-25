<?php

declare(strict_types=1);

namespace Cyrnetix\Tests\MonetDB;

use PHPUnit\Framework\TestCase;
use React\EventLoop\LoopInterface;
use Cyrnetix\MonetDB\ConnectionInterface;
use Cyrnetix\MonetDB\Factory;

use function Clue\React\Block\await;

/**
 * Class BaseTestCase
 *
 * @package React\Tests\MonetDB
 */
class BaseTestCase extends TestCase
{
    /**
     * Get connection options
     *
     * @param bool $debug
     */
    protected function getConnectionOptions(bool $debug = false): array
    {
        // Can be controlled through ENV or by changing defaults in phpunit.xml
        return [
            'host'   => getenv('DB_HOST'),
            'port'   => (int)getenv('DB_PORT'),
            'dbname' => getenv('DB_DBNAME'),
            'user'   => getenv('DB_USER'),
            'passwd' => getenv('DB_PASSWD'),
        ] + ($debug ? ['debug' => true] : []);
    }

    /**
     * Get connection test string
     *
     * @param array $params
     * @return string
     */
    protected function getConnectionString(array $params = []): string
    {
        $parts = $params + $this->getConnectionOptions();

        return rawurlencode($parts['user']) . ':' . rawurlencode($parts['passwd']) . '@' . $parts['host'] . ':' . $parts['port'] . '/' . rawurlencode($parts['dbname']);
    }

    /**
     * @param LoopInterface $loop
     * @return ConnectionInterface
     */
    protected function createConnection(LoopInterface $loop): ConnectionInterface
    {
        $factory = new Factory($loop);
        $promise = $factory->createConnection($this->getConnectionString());

        return await($promise, $loop, 10.0);
    }
}
