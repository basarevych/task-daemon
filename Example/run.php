<?php

namespace Example;

$loader = require '../vendor/autoload.php';
$loader->add('Example', __DIR__ . '/..');

Daemon::setOptions([
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

$daemon = Daemon::getInstance();

if ($argc >= 2 && $argv[1] == 'kill') {
    $daemon->kill();
    exit;
}

$daemon->start();
$daemon->runTask('reverse', 'hello');
$daemon->runTask('reverse', 'hello');
