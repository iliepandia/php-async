<?php

namespace App\Console\Commands;

use Clue\React\Stdio\Stdio;
use Illuminate\Console\Command;
use React\EventLoop\Loop;

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

    protected function drawLayout(Stdio $stdio, array $progress, array $logs)
    {
        $totalBars = count($progress);

        // Draw progress bars
        foreach ($progress as $index => $value) {
            $bar = str_repeat('=', (int)($value / 1)) . str_repeat(' ', 100 - (int)($value / 1));
            $colorCode = "37"; //white...
            if($value < 100){
                $colorCode = "30"; //black...
            }
            $stdio->write("\033[" . ($index + 1) . ";0H\033[{$colorCode}m\033[1m" .
                sprintf("Bar %2d: [%s] %d%%", $index + 1, $bar, $value)); // Move cursor to progress bar line
        }

        // Draw logs below progress bars
        $stdio->write("\033[" . ($totalBars + 2) . ";0H\033[32mLogs:\n"); // Move cursor below progress bars
        foreach (array_slice($logs, -10) as $log) { // Show the last 10 logs
            $stdio->write("  " . $log . "\n");
        }
    }
    /**
     * Execute the console command.
     */
    public function handle()
    {

        $loop = Loop::get();
        $stdio = new Stdio( $loop );
        $totalBars = 10;

        //Clear Screen
        $stdio->write("\033[2J");


        $progress = array_fill(0, $totalBars, 0 );

        $logs = [];

        $loop->addPeriodicTimer(0.1, function() use (&$progress, &$logs, $totalBars, $stdio){
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
            $this->drawLayout($stdio, $progress, $logs);
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
