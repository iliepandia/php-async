<?php

namespace App\Requests;

use App\Requests\Contracts\LogsListener;

class LogsCollection
{
    public array $logs = [];

    /**
     * @var LogsListener[]
     */
    public array $listeners = [];

    public function addLog(mixed $log): void
    {
        $this->logs [] = is_string($log) ? $log : json_encode($log);
        //Keep only last 20 logs
        $this->logs = array_slice($this->logs, -20);

        $this->updateObservers();
    }

    protected function updateObservers(): void
    {
        foreach ($this->listeners as $listener) {
            $listener->logsUpdated($this->logs);
        }
    }

    public function addListener(LogsListener $listener): void
    {
        $this->listeners [] = $listener;
    }

}
