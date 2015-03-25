<?php

namespace Example;

use TaskDaemon\AbstractTask;

class ReverseTask extends AbstractTask
{
    public function run()
    {
        echo "REVERSE start" . PHP_EOL;
        sleep(10);

        $data = $this->getData();

        echo "REVERSE end: " . strrev($data) . PHP_EOL;
    }
}
