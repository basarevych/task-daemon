<?php

namespace Example;

use TaskDaemon\TaskDaemon;

// Autoloader stuff, you probably don't need it
$loader = require '../vendor/autoload.php';
$loader->add('Example', __DIR__ . '/..');

// Configure TaskDaemon (optional)
// NOTE: 'namespace' and 'pid_file' must be the same
//        among all scripts that share the same daemon
TaskDaemon::setOptions([
    'namespace' => 'ExampleDaemon',
    'pid_file' => '/var/tmp/daemon-example.pid',
    'debug' => true,
]);

// Get the TaskDaemon instance (singletone)
$daemon = TaskDaemon::getInstance();

// Define our tasks. This is required in order to start() or restart()
$daemon->defineTask('reverse', new ReverseWordTask());
$daemon->defineTask('infinite', new InfiniteTask());

// Check Gearman is up and running
if (!$daemon->ping())
    exit(1);

// (Re)start the daemon
if ($daemon->getPid() === false)
    $daemon->start();
else
    $daemon->restart();
