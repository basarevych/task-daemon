<?php

namespace Example;

use TaskDaemon\TaskDaemon;

// Autoloader stuff, you probably don't need it
$loader = require '../vendor/autoload.php';
$loader->add('Example', __DIR__ . '/..');

// Get the string to print in reverse
if ($argc < 2) {
    echo "Give me a word as an argument" . PHP_EOL;
    exit(1);
}
$word = $argv[1];

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

// Run the task
$daemon->runTask('reverse', $word);
