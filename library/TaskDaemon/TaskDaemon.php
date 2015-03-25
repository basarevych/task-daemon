<?php
/**
 * TaskDaemon
 *
 * @link        https://github.com/basarevych/task-daemon
 * @copyright   Copyright (c) 2015 basarevych@gmail.com
 * @license     http://choosealicense.com/licenses/mit/ MIT
 */

namespace TaskDaemon;

use GearmanClient;
use GearmanWorker;
use Memcached;

/**
 * Daemon class (singleton)
 * 
 * @category    TaskDaemon
 */
class TaskDaemon
{
    /**
     * Daemon options
     *
     * @var array
     */
    protected static $options = [
        'namespace' => 'TaskDaemon',
        'pid_file'  => '/var/run/local-daemon.pid',
        'debug' => false,
        'gearman' => [
            'host' => 'localhost',
            'port' => 4730,
        ],
        'memcached' => [
            'host' => 'localhost',
            'port' => 11211,
        ],
    ];

    protected $started = false;
    protected $tasks = [];
    protected $memcached = null;

    /**
     * Worker PIDs
     *
     * @var array
     */
    protected $pids = [];

    /**
     * Constructor
     */
    protected function __construct()
    {
        $options = static::getOptions();

        if (!isset($options['namespace']))
            throw new \Exception("No namespace in the config");

        if (!isset($options['pid_file']))
            throw new \Exception("No pid_file in the config");

        if (!isset($options['memcached']['host']))
            throw new \Exception("No memcached host in the config");
        if (!isset($options['memcached']['port']))
            throw new \Exception("No memcached port in the config");

        if (!isset($options['gearman']['host']))
            throw new \Exception("No gearman host in the config");
        if (!isset($options['gearman']['port']))
            throw new \Exception("No gearman port in the config");

        $this->memcached = new Memcached();
        $this->memcached->addServer($options['memcached']['host'], $options['memcached']['port']);
    }

    public function setSharedVar($name, $value)
    {
        $options = static::getOptions();

        $this->memcached->set($options['namespace'] . '_' . $name, $value);
        return $this;
    }

    public function getSharedVar($name, $default)
    {
        $options = static::getOptions();

        $value = $this->memcached->get($options['namespace'] . '_' . $name);
        $result = $this->memcached->getResultCode();
        return $result == Memcached::RES_NOTFOUND ? $default : $value;
    }

    public function defineTask($name, $object)
    {
        if ($this->started)
            throw new \Exception("Define tasks before start()ing the daemon");

        if (! $object instanceof AbstractTask)
            throw new \Exception("Task must implement AbstractTask");

        $object->setDaemon($this);
        $this->tasks[$name] = $object;

        return $this;
    }

    public function runTask($name, $data = null, $allowDuplicates = false)
    {
        $options = static::getOptions();
        $debug = @$options['debug'] === true;

        if ($debug)
            echo "Adding task: $name" . PHP_EOL;

        $unique = $allowDuplicates ? self::generateUnique() : $name;

        $gmClient = new GearmanClient();
        $gmClient->addServer($options['gearman']['host'], $options['gearman']['port']);

        $gmClient->doBackground($options['namespace'] . '_' . $name, json_encode($data), $unique);
        $code = $gmClient->returnCode();
        if ($code != GEARMAN_SUCCESS)
            throw new \Exception("Could not add task: $name ($code)");

        return $this;
    }

    public function ping()
    {
        $options = static::getOptions();
        $debug = @$options['debug'] === true;

        $gmClient = new GearmanClient();
        $gmClient->addServer($options['gearman']['host'], $options['gearman']['port']);

        $ping = $gmClient->ping(self::generateUnique());

        if ($debug)
            echo "Pinging job server: " . ($ping ? 'Success' : 'Failure') . PHP_EOL;

        $code = $gmClient->returnCode();
        if ($code != GEARMAN_SUCCESS)
            throw new \Exception("Could not add task: $name ($code)");

        return $ping;
    }

    public function start()
    {
        $this->started = true;

        $options = static::getOptions();
        $debug = @$options['debug'] === true;

        $pidFile = $options['pid_file'];
        if (!$pidFile)
            throw new \Exception("No pid_file in the config");

        $fpPid = fopen($pidFile, "c");
        if (!$fpPid)
            throw new \Exception("Could not open " . $pidFile);

        if (!flock($fpPid, LOCK_EX | LOCK_NB)) {
            fclose($fpPid);
            if ($debug)
                echo "Daemon already running" . PHP_EOL;
            return $this;
        }

        if ($debug)
            echo "Daemonizing process" . PHP_EOL;

        $fork = pcntl_fork();
        if ($fork < 0)
            throw new \Exception("fork() failed: $fork");
        else if ($fork)
            return $this;

        ftruncate($fpPid, 0);
        fwrite($fpPid, getmypid() . PHP_EOL);
        fflush($fpPid);

        declare(ticks = 1);

        $exitCallback = function ($signo) use ($fpPid, $pidFile, $debug) {
            if (count($this->pids) == 0)
                exit;

            if ($debug)
                echo "Cleaning and exiting" . PHP_EOL;

            foreach ($this->pids as $pid)
                posix_kill($pid, SIGKILL);

            foreach ($this->pids as $pid)
                pcntl_waitpid($pid, $status);

            flock($fpPid, LOCK_UN);
            fclose($fpPid);
            @unlink($pidFile);

            exit;
        };

        pcntl_signal(SIGTERM, $exitCallback);
        pcntl_signal(SIGINT, $exitCallback);
        pcntl_signal_dispatch();

        $sid = posix_setsid();
        if ($sid < 0)
            throw new \Exception("setsid() failed: $sid");

        $gmWorker = new GearmanWorker();
        $gmWorker->addServer($options['gearman']['host'], $options['gearman']['port']);

        foreach ($this->tasks as $name => $object) {
            $task = function ($job) use ($name, $object, $options) {
                if (!isset($this->tasks[$name])) {
                    if (@$options['debug'] === true)
                        echo "Unknown task: $name" . PHP_EOL;
                    return;
                }

                if (@$options['debug'] === true)
                    echo "Running worker for: $name" . PHP_EOL;

                $worker = clone $object;
                $data = json_decode($job->workload(), true);
                $worker->setData($data);
                $worker->run();
            };
            $gmWorker->addFunction($options['namespace'] . '_' . $name, $task);
        }

        while (true) {
            $dead = pcntl_waitpid(-1, $status, WNOHANG);
            while ($dead > 0) {
                if (@$options['debug'] === true)
                    echo "Worker died: $dead" . PHP_EOL;

                $index = array_search($dead, $this->pids);
                if ($index !== false)
                    unset($this->pids[$index]); 

                $dead = pcntl_waitpid(-1, $status, WNOHANG);
            }

            while (count($this->pids) < 10) {
                $pid = pcntl_fork();
                if ($pid == 0) {
                    $this->pids = [];
                    if ($gmWorker->work()) {
                        $code = $gmWorker->returnCode();
                        if ($code != GEARMAN_SUCCESS && @$options['debug'] === true)
                            echo "Worker failed: $code";
                    }
                    exit;
                } else if ($pid > 0) {
                    $this->pids[] = $pid;
                }
            }

            sleep(1);
        }
    }

    public function kill()
    {
        $options = static::getOptions();
        $debug = @$options['debug'] === true;

        $pidFile = $options['pid_file'];
        if (!$pidFile)
            throw new \Exception("No pid_file in the config");

        $fpPid = fopen($pidFile, "c");
        if (!$fpPid)
            throw new \Exception("Could not open " . $pidFile);

        if (flock($fpPid, LOCK_EX | LOCK_NB)) {
            flock($fpPid, LOCK_UN);
            fclose($fpPid);
            if ($debug)
                echo "Daemon not running" . PHP_EOL;
            return;
        }

        fclose($fpPid);
        $pid = (int)file_get_contents($pidFile);
        if ($debug)
            echo "Killing PID $pid..." . PHP_EOL;

        posix_kill($pid, SIGTERM);
        pcntl_waitpid($pid, $status);
    }

    /**
     * Prevent cloning of the instance of the Singleton instance.
     */
    private function __clone()
    {
    }

    /**
     * Prevent unserializing of the Singleton instance.
     */
    private function __wakeup()
    {
    }

    /**
     * Returns the Singleton instance of this class.
     *
     * @return AbstractDaemon
     */
    public static function getInstance()
    {
        static $instance = null;
        if ($instance === null)
            $instance = new static();

        return $instance;
    }

    /**
     * Set options
     *
     * @param array $options
     */
    public static function setOptions($options)
    {
        foreach ($options as $key => $value)
            static::$options[$key] = $value;
    }

    /**
     * Get options
     *
     * @return array
     */
    public static function getOptions()
    {
        return static::$options;
    }

    public static function generateUnique()
    {
        $randomData = openssl_random_pseudo_bytes(1024);
        if ($randomData === false)
            throw new \Exception('Could not generate random string');

        return substr(hash('sha512', $randomData), 0, 20);
    }
}
