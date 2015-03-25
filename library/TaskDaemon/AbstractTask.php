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
    protected $daemon = null;
    protected $data = null;

    public function setDaemon($daemon)
    {
        $this->daemon = $daemon;
    }

    public function getDaemon()
    {
        return $this->daemon;
    }

    public function setData($data)
    {
        $this->data = $data;
    }

    public function getData()
    {
        return $this->data;
    }

    abstract public function run();
}
