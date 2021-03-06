Task Daemon
===========

Simple PHP fork()ing daemon which runs taks in the background. It uses
Gearman internally, but it is not exposed to the user.

Installation
------------

This daemon requires a running Gearman server and the following PHP extensions:

* pcntl
* posix
* openssl
* gearman

Add to require section of your composer.json:

```
    "basarevych/task-daemon": "0.2.*"
```
 
Examples
--------

Have a look at Example directory.

* ReverseWordTask.php - our example task
* InfiniteTask.php - another example (infinitely running)
* The rest is our example programs

```shell
Initialize:
> git clone https://github.com/basarevych/task-daemon
> cd task-daemon
> ./composer.phar install
> cd Example

Start or restart the daemon:
> php 1.start-the-daemon.php

Print 'foobar' in reverse:
> php 2.reverse-word-task.php foobar

Launch our long running task:
> php 3.infinite-task.php

Terminate:
> php 4.stop-the-daemon.php
```

You can run steps 2 and 3 several times, but if the task is already executing
(and is not terminated yet) running it again will do nothing.

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

  **NOTE** 'namespace' and 'pid_file' must be the same
  among all scripts that share the same daemon.

  **NOTE** If 'debug' is false (the default) then no text output
  from the daemon or its tasks will be printed to console.

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

* **getPid()**

  Returns PID of the daemon or false if the daemon is not running.

* **start()**

  Fork() to the background and start executing jobs. Could be called several times as only the first
  run will fork().

* **stop()**

  Terminate the daemon and its workers

* **restart()**

  Restart the daemon
