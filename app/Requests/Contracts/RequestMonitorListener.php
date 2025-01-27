<?php

namespace App\Requests\Contracts;

use App\Requests\MonitorCollection;

interface RequestMonitorListener
{
    public function progressUpdated( MonitorCollection $monitorCollection ) : void;
}
