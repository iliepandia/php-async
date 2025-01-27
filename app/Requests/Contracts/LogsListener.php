<?php

namespace App\Requests\Contracts;

interface LogsListener
{
    public function logsUpdated( array $logs ) : void;
}
