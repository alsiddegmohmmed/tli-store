<?php

use App\Domain\Sales\RecordSale;
use App\Domain\Shifts\CloseShift;
use App\Domain\Shifts\OpenShift;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.connections.pgsql.database') !== 'tli_store_test') {
    throw new RuntimeException('Isolated test database required.');
}
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
file_put_contents($argv[2], 'ready');
$deadline = microtime(true) + 10;
while (! is_file($argv[1])) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Test start gate timed out.');
    }
    usleep(10000);
    clearstatcache(true, $argv[1]);
}
try {
    $user = User::findOrFail($input['user_id']);
    $result = match ($input['action'] ?? 'sale') {
        'sale' => app(RecordSale::class)->execute($user, $input['data']),
        'open_shift' => app(OpenShift::class)->execute($user, $input['data']),
        'close_shift' => app(CloseShift::class)->execute($user, $input['data']['shift_id'], $input['data']['counted_cash'], null),
    };
    echo json_encode(['ok' => true, 'id' => $result->id], JSON_THROW_ON_ERROR);
} catch (ValidationException $error) {
    echo json_encode(['ok' => false, 'errors' => $error->errors()], JSON_THROW_ON_ERROR);
} catch (HttpException $error) {
    echo json_encode(['ok' => false, 'status' => $error->getStatusCode()], JSON_THROW_ON_ERROR);
}
