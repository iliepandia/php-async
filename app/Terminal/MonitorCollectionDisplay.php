<?php

namespace App\Terminal;

use App\Requests\Contracts\LogsListener;
use App\Requests\Contracts\RequestMonitorListener;
use App\Requests\MonitorCollection;
use Illuminate\Console\OutputStyle;
use Symfony\Component\Console\Cursor;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Terminal;

class MonitorCollectionDisplay implements RequestMonitorListener, LogsListener
{
    public ?MonitorCollection $monitorCollection = null;
    public ?array $logs = null;

    public int $width = 0;
    public int $height = 0;
    public function __construct( public OutputStyle $output )
    {
        //Get the size of the terminal - I might use this to shrink the progress bars
        $terminal = new Terminal();
        $this->width = $terminal->getWidth();
        $this->height = $terminal->getHeight();

        //Clear up the console screen
        $cursor = new Cursor($this->output);
        $cursor->clearScreen();

        //Define some styles for the console printer
        $style = new OutputFormatterStyle('black', 'default', ['bold']);
        $this->output->getFormatter()->setStyle('gray', $style );
        $style = new OutputFormatterStyle('white', 'default', ['bold']);
        $this->output->getFormatter()->setStyle('complete', $style );

    }

    public function progressUpdated(MonitorCollection $monitorCollection): void
    {
        $this->monitorCollection = $monitorCollection;
        $this->updateTerminal();
    }

    protected function updateTerminal(): void{
        $totalBars = $this->monitorCollection ? count($this->monitorCollection->requestMonitors) : 0 ;

        $cursor = new Cursor($this->output);

        $cursor->moveToPosition(1, 0 );
        $this->output->write( "<complete>Requests Progress:</complete>\n");

        // Draw progress bars
        foreach ($this->monitorCollection?->requestMonitors??[] as $requestMonitor) {
            $value = $requestMonitor->progress;
            $status = strtoupper(substr($requestMonitor->status, 0, 1));
            $barLength = $this->width - strlen( "Req xx: (C) [] 100%   ");
            $progressLength = ceil( $value / 100.0 * $barLength );
            $bar = str_repeat('=', $progressLength) . str_repeat(' ', $barLength-$progressLength );
            $style = "complete";
            if($requestMonitor->status != 'complete'){
                $style = "gray";
            }
            if($status == 'W'){
                $status = "<comment>$status</comment>";
            }
            if($status == 'R'){
                $status = "<info>$status</info>";
            }
            $this->output->write(sprintf("<$style>   Req %2d: [%s] [%s] %d%%</$style>\n", $requestMonitor->id, $status, $bar, $value));
        }

        // Draw logs below progress bars
        $logs = $this->logs ?? [];
        $cursor->moveToPosition(1, $totalBars + 1 );
        $cursor->clearLineAfter();
        $cursor->moveToPosition(1, $totalBars + 2 );
        $this->output->write("<info>Logs:</info>");
        $cursor->clearLineAfter();
        $cursor->moveDown();
        $cursor->moveToColumn(0);
        foreach (array_slice($logs, -10) as $log) { // Show the last 10 logs
            $this->output->write("  <gray>" . $log . "</gray>");
            $cursor->clearLineAfter();
            $cursor->moveDown();
            $cursor->moveToColumn(0);
        }
    }

    public function logsUpdated(array $logs): void
    {
        $this->logs = $logs;
        $this->updateTerminal();
    }
}
