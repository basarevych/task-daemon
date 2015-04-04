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
 
Usage
-----

Simple task example:

```php
namespace Example;

use TaskDaemon\AbstractTask;

class ReverseTask extends AbstractTask
{
    public function run(&$exitRequested)
    {
        $data = $this->getData();
        $result = strrev($data);
        file_put_contents('/tmp/result', $result);
    }
}
```

Now execute it in the background:

```php
namespace Example;

use TaskDaemon\TaskDaemon;

TaskDaemon::setOptions([
    'namespace' => 'ExampleDaemon',
    'num_workers' => 5,
    'pid_file' => '/tmp/daemon-example.pid',
]);

$daemon = TaskDaemon::getInstance();
$daemon->defineTask('reverse', new ReverseTask());

$daemon->start();
$daemon->runTask('reverse', 'hello');
```

This will save 'olleh' into a file. The daemon will continue to wait for commands (runTask()s)
in the background.

Ifinite background task example:

```php
namespace Example;

use TaskDaemon\AbstractTask;

class InfiniteTask extends AbstractTask
{
    public function run(&$exitRequested)
    {
        echo "start" . PHP_EOL;

        while (!$exitRequested) {
            echo "Job cycle here" . PHP_EOL;
            sleep(1);
        }

        echo "end: " . PHP_EOL;
    }
}
```

```php
$daemon = TaskDaemon::getInstance();
$daemon->defineTask('infinite', new InfiniteTask());

$daemon->runTask('infinite');
```

More examples (see run.php):
```shell
> cd Example
The following line could be executed several times, only first run will create a daemon:
> php run.php
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
