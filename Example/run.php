<?php

namespace Example;

use TaskDaemon\TaskDaemon;

// Autoloader stuff, you probably don't need it
$loader = require '../vendor/autoload.php';
$loader->add('Example', __DIR__ . '/..');

// Configure TaskDaemon (optional)
TaskDaemon::setOptions([
    'namespace' => 'ExampleDaemon',
    'num_workers' => 10,
    'pid_file' => '/var/tmp/daemon-example.pid',
    'debug' => true,
    'gearman' => [ // Gearman server
        'host' => 'localhost',
        'port' => 4730,
    ],
]);

// Get the TaskDaemon instance (singletone)
$daemon = TaskDaemon::getInstance();

if ($argc >= 2) {
    switch ($argv[1]) {
        case 'stop':
            // Tell the daemon to terminate
            $daemon->stop();
            break;
        case 'restart':
            // Restart the daemon
            $daemon->restart();
            break;
    }
} else {
    // Check Gearman is up and running
    if (!$daemon->ping())
        exit(1);

    // Define our tasks
    $daemon->defineTask('reverse', new ReverseTask());

    // Daemonize if there is no daemon already in which case this will do nothing
    $daemon->start();

    // Run the task
    $daemon->runTask('reverse', 'hello');

    // This will do nothing as we have already lunched this task
    $daemon->runTask('reverse', 'hello');

    // You can rerun "php run.php" anytime you want now
    // Our daemon is started now so it will do nothing
}
