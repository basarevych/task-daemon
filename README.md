Task Daemon
===========

Simple PHP fork()ing daemon which runs taks in the background. It uses
Gearman internally, but it is not exposed to the user.

Installation
------------

This daemon requires running Gearman server and the following PHP extensions:

* pcntl
* posix
* openssl
* gearman

Add to require section of composer.json:

```
    "basarevych/task-daemon": "0.2.*"
```
 
Examples
--------

Have a look at Example directory. Here you will find ReverseTask.php which is our example task,
InfiniteTask.php which is another example task and run.php which is our program.

```shell
> cd Example

The following could be executed several times, only first run will create a daemon,
later invocations will just simply try to run the tasks:
> php run.php

Restart the daemon:
> php run.php restart

Terminate the daemon:
> php run.php stop
```

Methods
-------

* **static TaskDaemon::setOptions()**

  The options:

  ```php
    'namespace' => 'TaskDaemon',                // Set to your project name
    'num_workers' => 10,                        // Number of parallel workers
    'pid_file'  => '/var/run/local-daemon.pid', // PID file path
    'debug' => false,                           // Make the daemon print debug info
    'gearman' => [                              // Gearman options
        'host' => 'localhost',
        'port' => 4730,
    ],
  ```

* **static TaskDaemon::getInstance()**

  Get daemon instance (the class is Singleton)

* **defineTask($name, $object)**

  Define a task. **$name** should be a unique string and **$object** must be an instance of a class
  derived from **AbstractTask**.

* **runTask($name, $data = null, $allowDuplicates = false)**

  Run previously defined task. **$data** will be passed to the task (could be an integer, string or array).

  If **$allowDuplicates** is true, several tasks of the same name and the same data could be run at the same time.

  For example, while runTask('task1', "data1") is executing call to the same task: runTask("task1", "data1")
  will be ignored. But task with a different name or data: runTask("task1", "otherdata") or runTask("task2")
  will be run.

* **ping()**

  Return true if we are connected to Gearman server.

* **start()**

  Fork() to the background and start executing jobs. Could be called several times as only the first
  run will fork().

* **stop()**

  Terminate the daemon and its workers

* **restart()**

  Restart the daemon
