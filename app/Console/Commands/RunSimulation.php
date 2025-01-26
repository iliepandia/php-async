<?php

namespace App\Console\Commands;

use Clue\React\Stdio\Stdio;
use Illuminate\Console\Command;
use React\EventLoop\Loop;
use Symfony\Component\Console\Cursor;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Terminal;

class RunSimulation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'run:simulation';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    protected function drawLayout( array $progress, array $logs)
    {
        $totalBars = count($progress);

        $cursor = new Cursor($this->output);

        $cursor->moveToPosition(1, 0 );
        $this->output->write( "<complete>Requests Progress:</complete>\n");

        // Draw progress bars
        foreach ($progress as $index => $value) {
            $bar = str_repeat('=', (int)($value / 1)) . str_repeat(' ', 100 - (int)($value / 1));
            $style = "complete";
            if($value < 100){
                $style = "gray";
            }
            $this->output->write(sprintf("<$style>   Req %2d: [%s] %d%%</$style>\n", $index + 1, $bar, $value));
        }

        // Draw logs below progress bars
        $cursor->moveToPosition(1, $totalBars + 2 );
        $this->output->write("<complete>Logs:</complete>\n");
        foreach (array_slice($logs, -10) as $log) { // Show the last 10 logs
            $this->output->write("  <gray>" . $log . "</gray>\n");
        }
    }
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $loop = Loop::get();
        $totalBars = 10;

        //Get the size of the terminal - I might use this to shrink the progress bars
        $terminal = new Terminal();
        $width = $terminal->getWidth();
        $height = $terminal->getHeight();

        //Clear up the console screen
        $cursor = new Cursor($this->output);
        $cursor->clearScreen();

        //Define some styles for the console printer
        $style = new OutputFormatterStyle('black', 'default', ['bold']);
        $this->getOutput()->getFormatter()->setStyle('gray', $style );
        $style = new OutputFormatterStyle('white', 'default', ['bold']);
        $this->getOutput()->getFormatter()->setStyle('complete', $style );

        $progress = array_fill(0, $totalBars, 0 );

        $logs = [];

        $loop->addPeriodicTimer(0.1, function() use (&$progress, &$logs, $totalBars){
            for ($i = 0; $i < $totalBars; $i++) {
                if ($progress[$i] < 100) {
                    $progress[$i] += rand(1, 5); // Random progress increment
                    if ($progress[$i] > 100) {
                        $progress[$i] = 100;
                    }
                }
            }

            // Add a new log entry
            $logs[] = "Progress updated at " . date('H:i:s');

            // Redraw the layout
            $this->drawLayout($progress, $logs);
        });

        // Stop the loop once all progress bars are at 100%
        $loop->addPeriodicTimer(0.5, function () use (&$progress, $loop) {
            if (count(array_filter($progress, fn($p) => $p < 100)) === 0) {
                $loop->stop();
            }
        });

        $loop->run();

    }
}
