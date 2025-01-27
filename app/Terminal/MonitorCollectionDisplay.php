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

    protected function initializeTerminalDisplay(): void
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
        $this->output->getFormatter()->setStyle('gray', $style);
        $style = new OutputFormatterStyle('white', 'default', ['bold']);
        $this->output->getFormatter()->setStyle('complete', $style);
    }

    public function __construct(public OutputStyle $output)
    {
        $this->initializeTerminalDisplay();
    }

    public function progressUpdated(MonitorCollection $monitorCollection): void
    {
        $this->monitorCollection = $monitorCollection;
        $this->updateTerminalDisplay();
    }

    protected function getStatusChar($requestMonitor): string
    {
        $status = strtoupper(substr($requestMonitor->status, 0, 1));
        if ($status == 'W') {
            $status = "<comment>$status</comment>";
        }
        if ($status == 'R') {
            $status = "<info>$status</info>";
        }

        return $status;
    }

    protected function getProgressBar($value): string
    {
        //We need to reserve room for all these chars.
        $reservedSpace = strlen("  Req xx: (C) [] 100%   ");

        $barLength = $this->width - $reservedSpace;

        $progressLength = ceil($value / 100.0 * $barLength);

        return str_repeat('=', $progressLength) .
            str_repeat('.', $barLength - $progressLength);
    }

    protected function drawProgressBars(): void
    {
        $this->output->write("<complete>Requests Progress:</complete>\n");

        // Draw progress bars
        foreach ($this->monitorCollection?->requestMonitors ?? [] as $requestMonitor) {
            $value = $requestMonitor->progress;
            $status = $this->getStatusChar($requestMonitor);
            $bar = $this->getProgressBar($value);
            $style = "complete";
            if ($requestMonitor->status != 'complete') {
                $style = "gray";
            }
            $this->output->write(sprintf("<$style>  Req %2d: [%s] [%s] %d%%</$style>\n",
                $requestMonitor->id, $status, $bar, $value));
        }
    }

    protected function drawLogs(Cursor $cursor): void
    {
        $logs = $this->logs ?? [];
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

    protected function updateTerminalDisplay(): void
    {
        $totalBars = $this->monitorCollection ? count($this->monitorCollection->requestMonitors) : 0;

        $cursor = new Cursor($this->output);

        //Hide cursor to avoid flickering...
        $cursor->hide();

        $cursor->moveToPosition(1, 0);

        $this->drawProgressBars();

        // Draw logs below progress bars
        $cursor->moveToPosition(1, $totalBars + 1);
        $cursor->clearLineAfter();
        $cursor->moveToPosition(1, $totalBars + 2);
        $this->drawLogs($cursor);

        $cursor->show();
    }

    public function logsUpdated(array $logs): void
    {
        $this->logs = $logs;
        $this->updateTerminalDisplay();
    }
}
