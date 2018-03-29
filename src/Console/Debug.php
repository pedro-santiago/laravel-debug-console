<?php

namespace Madewithlove\LaravelDebugConsole\Console;

use DebugBar\DebugBar;
use Illuminate\Console\Command;
use InvalidArgumentException;

class Debug extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:debug';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Displays laravel debug bar stored information.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        /** @var DebugBar $debug */
        $debug = app('debugbar');
        $debug->boot();

        // Watch files every second
        $profileData = [];
        while (true) {
            try {
                $latestFile = $debug->getStorage()->find([], 1);
            } catch (InvalidArgumentException $exception) {
                $this->refresh();
                $this->error($exception->getMessage());

                continue;
            }

            $id = array_get($latestFile, '0.id');

            // No file found so continue
            if (empty($id)) {
                $this->refresh();
                $this->warn('No laravel debugbar stored files available. Do a request and make sure APP_DEBUG is set to true.');

                continue;
            }

            // Latest file is the same do not render
            if (array_get($profileData, '__meta.id') === $id) {
                $this->wait();

                continue;
            }

            $profileData = $debug->getStorage()->get($id);

            $this->refresh();
            $this->info('Latest Request Information:');
            $this->renderMetaData($profileData);
            $this->renderQueryData($profileData);
        }
    }

    /**
     * @param array $data
     */
    public function renderMetaData(array $data)
    {
        $this->table([
            'Date and Time',
            'Total Memory',
            'Total Time',
        ], [
            [
                array_get($data, '__meta.datetime'),
                array_get($data, 'memory.peak_usage_str'),
                array_get($data, 'time.duration_str')
            ]
        ]);
    }

    public function wait()
    {
        sleep(1);
    }

    public function refresh()
    {
        $this->wait();
        system('clear');
    }

    /**
     * @param array $data
     */
    public function renderQueryData(array $data)
    {
        $queries = collect(array_get($data, 'queries.statements', []));

        // Retrieve grouped sql statements
        $rows = $queries
            ->map(function ($query, $index) {
                return [
                    $index,
                    array_get($query, 'sql'),
                    array_get($query, 'duration_str'),
                ];
            })
            ->all();

        $this->info('Queries Executed:');
        $this->table([
            'N.',
            'SQL',
            'Duration',
        ], $rows);

        $this->info('Queries Summary:');
        $this->table([
            'Total Number of Queries',
            'Total Execution Time',
        ], [
            [
                array_get($data, 'queries.nb_statements'),
                array_get($data, 'queries.accumulated_duration_str')
            ]
        ]);
    }
}
