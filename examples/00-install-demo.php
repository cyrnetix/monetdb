<?php

declare(strict_types=1);

use Cyrnetix\MonetDB\Factory;
use Cyrnetix\MonetDB\QueryResult;
use React\EventLoop\Loop;

require __DIR__ . '/../vendor/autoload.php';

$stdout = shell_exec('docker exec monetdb-reactphp-example bash -c "monetdb create voc; monetdb release voc" 2>&1');
if (false !== stripos($stdout, 'error')) {
    die($stdout);
}

$source = 'https://dev.monetdb.org/Assets/VOC/voc_dump.sql.gz';
$buffer = 4096; // read 4kb at a time
$handle = gzopen($source, 'rb');

$sql = '';
while (!gzeof($handle)) {
     $sql .= gzread($handle, $buffer);
}
gzclose($handle);

// Create a lazy monetdb connection for executing query
$factory = new Factory(Loop::get());

$uri = 'monetdb:monetdb@localhost/voc';
$connection = $factory->createLazyConnection($uri);

$connection->query($sql)
    ->then(function (QueryResult $result) {
        echo sprintf('Affected rows: %d', $result->affectedRows);
    }, function (Throwable $error) {
        echo $error->getMessage();
    })
    ->always(function () use ($connection) {
        $connection->close();
        Loop::get()->stop();
    });
