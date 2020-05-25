<?php

// $ php examples/02-query-stream.php requires demo content

use Cyrnetix\MonetDB\Factory;

require __DIR__ . '/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();
$factory = new Factory($loop);

$uri = 'monetdb:monetdb@localhost/voc';
$query = $argv[1] ?? 'select * from voyages';

// Create a lazy mysql connection for executing query
$connection = $factory->createLazyConnection($uri);

$stream = $connection->queryStream($query);

$stream->on('data', function ($row) {
    echo json_encode($row, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES) . PHP_EOL;
});

$stream->on('error', function (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
});

$stream->on('close', function () {
    echo 'CLOSED' . PHP_EOL;
});

$connection->quit();

$loop->run();
