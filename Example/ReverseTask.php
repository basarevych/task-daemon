<?php

namespace Example;

use TaskDaemon\AbstractTask;

class ReverseTask extends AbstractTask
{
    public function run(&$exitRequested)
    {
        echo "REVERSE start" . PHP_EOL;

        while (!$exitRequested) {
            echo "Job cycle" . PHP_EOL;
            sleep(1);
        }

        $data = $this->getData();
        echo "REVERSE end: " . strrev($data) . PHP_EOL;
    }
}
