<?php

namespace Example;

use TaskDaemon\AbstractTask;

/**
 * Simple task that will print the string in reverse order
 * Set 'debug' option to true in order to see the text printed by this task
 */
class ReverseWordTask extends AbstractTask
{
    public function run(&$exitRequested)
    {
        echo "-> REVERSE STRING TASK STARTED" . PHP_EOL;

        $data = $this->getData();
        echo "-> DATA: " . $data . PHP_EOL;
        echo "-> RESULT: " . strrev($data) . PHP_EOL;

        echo "-> REVERSE STRING TASK ENDED" . PHP_EOL;
    }
}
