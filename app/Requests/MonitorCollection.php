<?php

namespace App\Requests;

use App\Requests\Contracts\RequestMonitorListener;

class MonitorCollection
{
    public array $requestMonitors = [];

    /**
     * @var RequestMonitorListener[]
     */
    public array $listeners = [];

    public function addMonitor(RequestMonitor $monitor ) : void{
        if(isset($this->requestMonitors[$monitor->id])){
            throw new \RuntimeException("Ooopsy! Monitor ID already in use." );
        }
        $this->requestMonitors[$monitor->id] = $monitor;
    }

    public function addListener( RequestMonitorListener $listener ): void
    {
        $this->listeners []= $listener;
    }

    public function updateMonitor(int $id, $downloadTotal, $downloadedBytes) : void
    {
        $monitor = $this->requestMonitors[$id];

        if($downloadTotal == 0 || $downloadedBytes == 0 ){
            $monitor->progress = 0;
            $monitor->status = 'waiting';
        }else{
            $monitor->progress = ceil( floatval($downloadedBytes) / $downloadTotal  * 100 );
            $monitor->status = 'receiving';
        }
        if($monitor->progress == 100 ){
            $monitor->status = 'complete';
        }

        //Notify all listeners that we have updates...
        foreach ($this->listeners as $listener){
            $listener->progressUpdated($this);
        }
    }
}
