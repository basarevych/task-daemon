<?php

namespace Example;

use TaskDaemon\TaskDaemon;

$loader = require '../vendor/autoload.php';
$loader->add('Example', __DIR__ . '/..');

TaskDaemon::setOptions([
    'namespace' => 'ExampleDaemon',
    'pid_file' => '/tmp/daemon-example.pid',
    'debug' => true,
    'gearman' => [
        'host' => 'localhost',
        'port' => 4730,
    ],
    'memcached' => [
        'host' => 'localhost',
        'port' => 11211,
    ],
]);

$daemon = TaskDaemon::getInstance();

if ($argc >= 2 && $argv[1] == 'kill') {
    $daemon->kill();
    exit;
}

var_dump($daemon->ping());

$daemon->defineTask('reverse', new ReverseTask());

$daemon->start();
$daemon->runTask('reverse', 'hello');
$daemon->runTask('reverse', 'hello');
