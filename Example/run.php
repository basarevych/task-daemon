<?php

namespace Example;

use TaskDaemon\TaskDaemon;

$loader = require '../vendor/autoload.php';
$loader->add('Example', __DIR__ . '/..');

TaskDaemon::setOptions([
    'namespace' => 'ExampleDaemon',
    'num_workers' => 10,
    'pid_file' => '/tmp/daemon-example.pid',
    'debug' => true,
]);

$daemon = TaskDaemon::getInstance();
$daemon->defineTask('reverse', new ReverseTask());

if ($argc >= 2) {
    switch ($argv[1]) {
        case 'stop':    $daemon->stop(); break;
        case 'restart': $daemon->restart(); break;
    }
    exit;
}

var_dump($daemon->ping());

$daemon->start();
$daemon->runTask('reverse', 'hello');
$daemon->runTask('reverse', 'hello');
