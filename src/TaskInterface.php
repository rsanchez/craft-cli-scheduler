<?php

namespace CraftCli\Scheduler;

use Crunz\Schedule;

interface TaskInterface
{
    /**
     * Schedule a task
     * @return \Crunz\Schedule
     */
    public function schedule();
}
