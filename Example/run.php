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

// Define our tasks
$daemon->defineTask('reverse', new ReverseTask());
$daemon->defineTask('infinite', new InfiniteTask());

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

    // Daemonize if there is no daemon already in which case this will do nothing
    $daemon->start();

    // Run the regular task
    $daemon->runTask('reverse', 'hello');

    // Try to run the infinite task
    // First invocation will launch the task, later will do nothing
    $daemon->runTask('infinite');

    // You can rerun "php run.php" anytime you want now
    // Our daemon is started now so it won't fork anymore
    // and will only try to relaunch the tasks
}
