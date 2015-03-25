<?php
/**
 * TaskDaemon
 *
 * @link        https://github.com/basarevych/task-daemon
 * @copyright   Copyright (c) 2015 basarevych@gmail.com
 * @license     http://choosealicense.com/licenses/mit/ MIT
 */

namespace TaskDaemon;

/**
 * Abstract task
 * 
 * @category    TaskDaemon
 */
abstract class AbstractTask
{
    /**
     * Daemon instance
     *
     * @var TaskDaemon
     */
    protected $daemon = null;

    /**
     * Task data
     *
     * @var mixed
     */
    protected $data = null;

    /**
     * Set daemon
     *
     * @param TaskDaemon $daemon
     * @return AbstractTask
     */
    public function setDaemon($daemon)
    {
        $this->daemon = $daemon;

        return $this;
    }

    /**
     * Get daemon
     *
     * @return TaskDaemon
     */
    public function getDaemon()
    {
        return $this->daemon;
    }

    /**
     * Set data
     *
     * @param mixed $data
     * @return AbstractTask
     */
    public function setData($data)
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Get data
     *
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Do the job
     */
    abstract public function run();
}
