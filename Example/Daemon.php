<?php

namespace Example;

use TaskDaemon\AbstractDaemon;

class Daemon extends AbstractDaemon
{
    public function init()
    {
        $this->defineTask('cron', new CronTask());
        $this->defineTask('reverse', new ReverseTask());
    }

    public function run()
    {
        $lastCron = $this->getSharedVar('LastCron', 0);
        if (time() - $lastCron > 60)
            $this->runTask('cron');
    }
}
