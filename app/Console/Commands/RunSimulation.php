<?php

namespace App\Console\Commands;

use App\Requests\LogsCollection;
use App\Requests\MonitorCollection;
use App\Requests\RequestMonitor;
use App\Terminal\MonitorCollectionDisplay;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\Utils;
use Illuminate\Console\Command;

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

    protected ?int $width;
    protected ?int $height;

    protected array $logs = [];

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
                    'fbd' => 6_000,
                ],
                [
                    'size' => 20,
                    'chunks' => 10,
                    'delay' => 1000,
                    'fbd' => 5_000,
                ],
                [
                    'size' => 20_000,
                    'chunks' => 14,
                    'delay' => 300,
                    'fbd' => 10_000,
                ],
                [
                    'size' => 3,
                    'chunks' => 20,
                    'delay' => 300,
                ],
                [
                    'size' => 3,
                    'chunks' => 20,
                    'delay' => 300,
                    'fbd' => 1_500,
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
                    'fbd' => 500,
                ],
            ],
        ];

        $this->requests = [];

        $terminalDisplay = new MonitorCollectionDisplay($this->output);

        $monitorCollection = new MonitorCollection();
        $monitorCollection->addListener($terminalDisplay);

        $logs = new LogsCollection();
        $logs->addListener($terminalDisplay);

        $promises = [];
        foreach ($configuration['requests'] as $key => $configuration) {
            $requestMonitor = new RequestMonitor($key + 1, 'waiting', 0);
            $monitorCollection->addMonitor($requestMonitor);
            $id = $requestMonitor->id;
            $this->requests[$requestMonitor->id] = $requestMonitor;
            $promise = $client->getAsync("/", [
                'query' => [
                    'size' => $configuration['size'],
                    'chunks' => $configuration['chunks'],
                    'delay' => $configuration['delay'],
                    'fbd' => $configuration['fbd'] ?? null,
                ],
                'progress' => function ($downloadTotal, $downloadedBytes) use ($id, $monitorCollection, $logs) {
                    //Update the progress tracker for the request
                    $monitorCollection->updateMonitor($id, $downloadTotal, $downloadedBytes);
                    $logs->addLog("Received progress report for request $id: {$this->requests[$id]->progress}%...");
                },
            ]);
            $logs->addLog("Initialized request: {$id}...");
            $promises [] = $promise;
        }
        $start = time();
        $responses = Utils::unwrap($promises);
        foreach ($this->requests as $request) {
            $logs->addLog("Request {$request->id} is complete.");
        }
        $end = time();
        $this->line("Completed in: <info>" . ($end - $start) . "</info> seconds");
    }
}
