<?php

namespace Tests\Support;

use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

trait RunsConcurrentOperations
{
    private function race(array $inputs): array
    {
        $gate = sys_get_temp_dir().'/tli-race-'.Str::uuid();
        $environment = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'pgsql', 'DB_DATABASE' => 'tli_store_test', 'DB_URL' => '',
            'DB_HOST' => config('database.connections.pgsql.host'), 'DB_PORT' => (string) config('database.connections.pgsql.port'),
            'DB_USERNAME' => config('database.connections.pgsql.username'), 'DB_PASSWORD' => config('database.connections.pgsql.password')];
        $workers = [];
        try {
            foreach ($inputs as $i => $input) {
                $worker = new Process([PHP_BINARY, base_path('tests/Support/record-operation.php'), $gate, $gate.'.'.$i], base_path(), $environment,
                    json_encode($input, JSON_THROW_ON_ERROR), 15);
                $worker->start();
                $workers[] = $worker;
            }
            $deadline = microtime(true) + 10;
            foreach ($workers as $i => $worker) {
                while (! is_file($gate.'.'.$i) && $worker->isRunning() && microtime(true) < $deadline) {
                    usleep(10000);
                    clearstatcache();
                }
                $this->assertFileExists($gate.'.'.$i, $worker->getErrorOutput());
            }
            file_put_contents($gate, 'start');
            $results = [];
            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput());
                $results[] = json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            }

            return $results;
        } finally {
            foreach ($workers as $i => $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                } if (is_file($gate.'.'.$i)) {
                    unlink($gate.'.'.$i);
                }
            }
            if (is_file($gate)) {
                unlink($gate);
            }
        }
    }
}
