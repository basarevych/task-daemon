<?php

namespace Example;

use TaskDaemon\AbstractTask;

class CronTask extends AbstractTask
{
    public function run()
    {
        echo "CRON" . PHP_EOL;
        $this->getDaemon()->setSharedVar('LastCron', time());
    }
}
