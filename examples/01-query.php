<?php

declare(strict_types=1);

use Cyrnetix\MonetDB\Factory;
use Cyrnetix\MonetDB\QueryResult;

require __DIR__ . '/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();
$factory = new Factory($loop);

$uri = 'monetdb:monetdb@localhost/voc';
$query = isset($argv[1]) ? $argv[1] : "SELECT 'hello world!'";

// Create a lazy connection for executing the query
$connection = $factory->createLazyConnection($uri);

$connection->query($query)->then(function (QueryResult $command) {
    if (isset($command->resultRows)) {
        // This is a response to a SELECT etc. with a row
        print_r($command->resultFields);
        print_r($command->resultRows);
        echo count($command->resultRows) . ' row(s) in set' . PHP_EOL;
    } else {
        // this is an OK message in response to an UPDATE etc.
        if ($command->insertId !== 0) {
            var_dump('last insert ID', $command->insertId);
        }
        echo 'Query OK, ' . $command->affectedRows . ' row(s) affected' . PHP_EOL;
    }
}, function (Exception $error) {
    // the query was not executed successfully
    echo 'Error: ' . $error->getMessage() . PHP_EOL;
});

$connection->quit();

$loop->run();
