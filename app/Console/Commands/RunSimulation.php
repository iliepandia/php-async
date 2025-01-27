<?php

namespace App\Console\Commands;

use GuzzleHttp\Client;
use GuzzleHttp\Promise\Utils;
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

    protected array $requests = [];

    protected array $logs = [];

    protected function logMessage($message){
        $message = is_string($message) ? $message : json_encode( $message );
        $this->logs[] = $message;
        $this->logs = array_slice($this->logs, -20);
    }

    public function handle()
    {
        $client = new Client([
           'base_uri' => 'http://localhost:9010/'
        ]);

        //ToDo - this will be read from a file
        $configuration = [
            'type' => 'async',
            'requests' => [
                [
                    'size' => 10,
                    'chunks' => 10,
                    'delay' => 500,
                ],
                [
                    'size' => 20,
                    'chunks' => 10,
                    'delay' => 1000,
                ],
                [
                    'size' => 20_000,
                    'chunks' => 14,
                    'delay' => 300,
                ],
                [
                    'size' => 3,
                    'chunks' => 20,
                    'delay' => 300,
                ],
                [
                    'size' => 30,
                    'chunks' => 25,
                    'delay' => 300,
                ],
                [
                    'size' => 30,
                    'chunks' => 200,
                    'delay' => 50,
                ],
            ],
        ];

        $this->requests = [];

        $promises = [];
        foreach($configuration['requests'] as $key => $configuration ){
            $request = [
                'id' => $key + 1,
                'status' => 'waiting',
                'progress' => 0,
            ];
            $id = $request['id'];
            $this->requests[$request['id']] = $request;
            $promise =  $client->getAsync("/", [
                'query' => [
                    'size' => $configuration['size'],
                    'chunks' => $configuration['chunks'],
                    'delay' => $configuration['delay'],
                ],
                'progress' => function($downloadTotal, $downloadedBytes) use ($id){
                    $this->requests[$id]['status'] = 'receiving';
                    if($downloadTotal == 0 || $downloadedBytes == 0 ){
                        $this->requests[$id]['progress'] = 0;
                    }else{
                        $this->requests[$id]['progress'] = ceil( floatval($downloadedBytes) / $downloadTotal  * 100 );
                    }
                    if($this->requests[$id]['progress'] == 100 ){
                        $this->requests[$id]['status'] = 'complete';
                    }
                    $this->logMessage("Received progress report for request $id: {$this->requests[$id]['progress']}%...");
                    $this->updateScreen();
                },
            ]);
            $this->logMessage("Initialized request: {$id}...");
            $promises []= $promise;
        }
        $this->initDisplay();
        $responses = Utils::unwrap($promises);
        foreach ($this->requests as $request){
            $this->requests[$request['id']]['status'] ='complete';
            $this->logMessage("Request {$request['id']} is complete.");
        }
        $this->updateScreen();
    }

    protected function updateScreen() : void
    {
        $totalBars = count($this->requests);

        $cursor = new Cursor($this->output);

        $cursor->moveToPosition(1, 0 );
        $this->output->write( "<complete>Requests Progress:</complete>\n");

        // Draw progress bars
        foreach ($this->requests as $index => $request) {
            $value = $request['progress'];
            $status = strtoupper(substr($request['status'], 0, 1));
            $bar = str_repeat('=', (int)($value / 1)) . str_repeat(' ', 100 - (int)($value / 1));
            $style = "complete";
            if($value < 100){
                $style = "gray";
            }
            $this->output->write(sprintf("<$style>   Req %2d: (%s) [%s] %d%%</$style>\n", $request['id'], $status, $bar, $value));
        }

        // Draw logs below progress bars
        $logs = $this->logs;
        $cursor->moveToPosition(1, $totalBars + 2 );
        $this->output->write("<complete>Logs:</complete>\n");
        foreach (array_slice($logs, -10) as $log) { // Show the last 10 logs
            $this->output->write("  <gray>" . $log . "</gray>");
            $cursor->clearLineAfter();
            $cursor->moveDown();
            $cursor->moveToColumn(0);
        }
    }

    protected function initDisplay()
    {
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
    }

}
