<?php

namespace Example;

use TaskDaemon\AbstractTask;

/**
 * This task will run indefinitely
 * Set 'debug' option to true in order to see the text printed by this task
 */
class InfiniteTask extends AbstractTask
{
    public function run(&$exitRequested)
    {
        echo "-> INFINITE TASK STARTED" . PHP_EOL;

        while (!$exitRequested) {
            // Imitate doing something while checking $exitRequested periodically
            echo "-> INIFINITE TASK JOB CYCLE (sleep 5 seconds)" . PHP_EOL;
            sleep(5);
        }

        echo "-> INFINITE TASK ENDED" . PHP_EOL;
    }
}
