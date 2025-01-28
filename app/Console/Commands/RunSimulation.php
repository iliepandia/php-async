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
    protected $signature = 'run:simulation {inputJsonFile}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    protected ?int $width;
    protected ?int $height;

    protected array $logs = [];

    protected function executeAsync($client, $configuration, $monitorCollection, $logs): array
    {
        $promises = [];
        foreach ($configuration['requests'] as $key => $requestConfiguration) {
            $requestMonitor = new RequestMonitor($key + 1, 'waiting', 0);
            $monitorCollection->addMonitor($requestMonitor);
            $id = $requestMonitor->id;
            $this->requests[$requestMonitor->id] = $requestMonitor;

            $promise = $client->getAsync("/", [
                'query' => $requestConfiguration,
                'progress' => function ($downloadTotal, $downloadedBytes) use ($id, $monitorCollection, $logs) {
                    //Update the progress tracker for the request
                    $monitorCollection->updateMonitor($id, $downloadTotal, $downloadedBytes);
                    $logs->addLog("Received progress report for request $id: {$this->requests[$id]->progress}%...");
                },
            ]);

            $logs->addLog("Initialized request: {$id}...");
            $promises [] = $promise;
        }
        return Utils::unwrap($promises);
    }

    protected function executeSync($client, $configuration, $monitorCollection, $logs): array
    {
        $responses = [];
        foreach ($configuration['requests'] as $key => $requestConfiguration) {
            $requestMonitor = new RequestMonitor($key + 1, 'waiting', 0);
            $monitorCollection->addMonitor($requestMonitor);
            $id = $requestMonitor->id;
            $this->requests[$requestMonitor->id] = $requestMonitor;

            $logs->addLog("Sending request: {$id}...");
            $responses []= $client->get("/", [
                'query' => $requestConfiguration,
                'progress' => function ($downloadTotal, $downloadedBytes) use ($id, $monitorCollection, $logs) {
                    //Update the progress tracker for the request
                    $monitorCollection->updateMonitor($id, $downloadTotal, $downloadedBytes);
                    $logs->addLog("Received progress report for request $id: {$this->requests[$id]->progress}%...");
                },
            ]);
            $logs->addLog("Request: {$id} complete.");
        }

        return $responses;
    }

    public function handle()
    {
        $client = new Client([
            'base_uri' => 'http://localhost:9010/'
        ]);

        $data = \Storage::disk('public')->get("/examples/" . $this->argument('inputJsonFile') );
        $configuration = json_decode($data,true);

        $terminalDisplay = new MonitorCollectionDisplay($this->output);

        $monitorCollection = new MonitorCollection();
        $monitorCollection->addListener($terminalDisplay);

        $logs = new LogsCollection();
        $logs->addListener($terminalDisplay);

        $start = time();

        if($configuration['type'] == 'async'){
            $this->executeAsync($client, $configuration, $monitorCollection, $logs);
        }else{
            $this->executeSync($client, $configuration, $monitorCollection, $logs);
        }

        $logs->addLog("All requests are complete.");
        $end = time();
        $this->line("Completed in: <info>" . ($end - $start) . "</info> seconds");
    }
}
