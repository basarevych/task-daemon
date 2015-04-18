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
        'num_workers' => 10,
        'pid_file'  => '/var/tmp/local-daemon.pid',
        'debug' => false,
        'gearman' => [
            'host' => 'localhost',
            'port' => 4730,
        ],
    ];

    /**
     * Daemon started
     *
     * @var boolean
     */
    protected $started = false;

    /**
     * Exit requested
     *
     * @var boolean
     */
    protected $exitRequested = false;

    /**
     * Known tasks
     *
     * @var array
     */
    protected $tasks = [];

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
        if (!isset($options['num_workers']))
            throw new \Exception("No num_workers in the config");

        if (!isset($options['pid_file']))
            throw new \Exception("No pid_file in the config");

        if (!isset($options['gearman']['host']))
            throw new \Exception("No gearman host in the config");
        if (!isset($options['gearman']['port']))
            throw new \Exception("No gearman port in the config");
    }

    /**
     * Add task to the list of known tasks
     *
     * @param string $name
     * @param AbstractTask $object
     * @return TaskDaemon
     */
    public function defineTask($name, $object)
    {
        $options = static::getOptions();
        $debug = @$options['debug'] === true;

        if ($this->started)
            throw new \Exception("Define tasks before start()ing the daemon not after");

        if (! $object instanceof AbstractTask)
            throw new \Exception("Task must implement AbstractTask");

        $object->setDaemon($this);
        $this->tasks[$name] = $object;

        if ($debug)
            echo "==> Task defined: $name" . PHP_EOL;

        return $this;
    }

    /**
     * Add known task to the run queue
     *
     * @param string $name
     * @param mixed $data
     * @param boolean $allowDuplicates
     * @return TaskDaemon
     */
    public function runTask($name, $data = null, $allowDuplicates = false)
    {
        $options = static::getOptions();
        $debug = @$options['debug'] === true;

        if ($debug)
            echo "==> Trying to run task: $name" . PHP_EOL;

        $function = $options['namespace'] . '-' . $name;
        $data = json_encode($data);
        if ($allowDuplicates)
            $unique = self::generateUnique();
        else
            $unique = md5($function . '-' . $data);

        $gmClient = new GearmanClient();
        $gmClient->addServer($options['gearman']['host'], $options['gearman']['port']);

        $gmClient->doBackground($function, $data, $unique);
        $code = $gmClient->returnCode();
        if ($code != GEARMAN_SUCCESS)
            throw new \Exception("Could not run task: $name ($code)");

        return $this;
    }

    /**
     * Ping job servers
     *
     * @return TaskDaemon
     */
    public function ping()
    {
        $options = static::getOptions();
        $debug = @$options['debug'] === true;

        $gmClient = new GearmanClient();
        $gmClient->addServer($options['gearman']['host'], $options['gearman']['port']);

        $ping = $gmClient->ping(self::generateUnique());

        if ($debug)
            echo "==> Pinging job server: " . ($ping ? 'Success' : 'Failure') . PHP_EOL;

        $code = $gmClient->returnCode();
        if ($code != GEARMAN_SUCCESS)
            throw new \Exception("Ping failed ($code)");

        return $ping;
    }

    /**
     * Start the daemon with workers (by fork()ing)
     *
     * @return TaskDaemon
     */
    public function start()
    {
        if (count($this->tasks) == 0)
            throw new \Exception("There are no tasks defined - can not start");

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
                echo "==> Daemon already running" . PHP_EOL;
            return $this;
        }

        if ($debug)
            echo "==> Daemonizing... " . PHP_EOL;

        $fork = pcntl_fork();
        if ($fork < 0)
            throw new \Exception("fork() failed: $fork");
        else if ($fork)
            return $this;

        ftruncate($fpPid, 0);
        fwrite($fpPid, getmypid() . PHP_EOL);
        fflush($fpPid);
        chmod($pidFile, 0666);

        declare(ticks = 1);

        $exitCallback = function ($signo) use ($fpPid, $pidFile, $debug) {
            if ($debug)
                echo "==> Cleaning and exiting" . PHP_EOL;

            foreach ($this->pids as $pid)
                posix_kill($pid, SIGTERM);
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
            $function = $options['namespace'] . '-' . $name;
            $task = function ($job) use ($name, $function, $object, $options) {
                if (@$options['debug'] === true)
                    echo "==> Running worker for: $function" . PHP_EOL;

                $worker = clone $object;
                $data = json_decode($job->workload(), true);
                $worker->setData($data);

                if (@$options['debug'] !== true)
                    ob_start();
                $worker->run($this->exitRequested);
                if (@$options['debug'] !== true)
                    ob_end_clean();
            };
            $gmWorker->addFunction($function, $task);
        }

        while (true) {
            $dead = pcntl_waitpid(-1, $status, WNOHANG);
            while ($dead > 0) {
                if (@$options['debug'] === true)
                    echo "==> Worker terminated: $dead" . PHP_EOL;

                $index = array_search($dead, $this->pids);
                if ($index !== false)
                    unset($this->pids[$index]); 

                $dead = pcntl_waitpid(-1, $status, WNOHANG);
            }

            while (count($this->pids) < $options['num_workers']) {
                $pid = pcntl_fork();
                if ($pid == 0) {
                    declare(ticks = 1);

                    $exitCallback = function ($signo) {
                        $this->exitRequested = true;
                    };

                    pcntl_signal(SIGTERM, $exitCallback);
                    pcntl_signal(SIGINT, $exitCallback);
                    pcntl_signal_dispatch();

                    $this->pids = [];
                    $gmWorker->setTimeout(1000);
                    while (!$this->exitRequested) {
                        $gmWorker->work();
                        $code = $gmWorker->returnCode();
                        switch ($code) {
                            case GEARMAN_TIMEOUT:
                                sleep(1);
                                break;
                            case GEARMAN_SUCCESS:
                                $this->exitRequested = true;
                                break;
                            default:
                                if (@$options['debug'] === true)
                                    echo "==> Worker failed: $code";
                                exit(1);
                        }
                    }
                    exit;
                } else if ($pid > 0) {
                    $this->pids[] = $pid;
                }
            }

            sleep(1);
        }
    }

    /**
     * Stop the daemon
     *
     * @return TaskDaemon
     */
    public function stop()
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
                echo "==> Daemon not running" . PHP_EOL;
            return;
        }

        fclose($fpPid);
        $pid = (int)file_get_contents($pidFile);
        if ($debug)
            echo "==> Killing the daemon (PID $pid)..." . PHP_EOL;

        posix_kill($pid, SIGTERM);
        pcntl_waitpid($pid, $status);
    }

    /**
     * Restart the daemon
     *
     * @return TaskDaemon
     */
    public function restart()
    {
        if (count($this->tasks) == 0)
            throw new \Exception("There are no tasks defined - can not restart");

        $this->stop();
        sleep(3);
        $this->start();
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

        return md5($randomData);
    }
}
