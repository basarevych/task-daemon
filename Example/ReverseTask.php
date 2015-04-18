<?php

namespace Example;

use TaskDaemon\AbstractTask;

class ReverseTask extends AbstractTask
{
    public function run(&$exitRequested)
    {
        echo "-> REVERSE TASK STARTED" . PHP_EOL;

        $data = $this->getData();
        echo "-> DATA: " . $data . PHP_EOL;
        echo "-> RESULT: " . strrev($data) . PHP_EOL;

        echo "-> REVERSE TASK ENDED" . PHP_EOL;
    }
}
