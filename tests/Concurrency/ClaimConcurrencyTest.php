<?php

use App\Actions\Secrets\ClaimSecret;
use App\Exceptions\Secrets\SecretUnavailable;
use App\Models\Secret;
use Illuminate\Support\Facades\DB;

/**
 * Fork $workers processes that claim the same secret at the same instant,
 * each on its own PostgreSQL connection. Returns outcome => count.
 *
 * @return array<string, int>
 */
function claimConcurrently(string $publicId, int $workers): array
{
    $resultsDirectory = sys_get_temp_dir().'/keyveil-claims-'.bin2hex(random_bytes(6));
    mkdir($resultsDirectory);

    $startAt = microtime(true) + 0.5;
    $processIds = [];

    DB::disconnect();

    foreach (range(1, $workers) as $worker) {
        $processId = pcntl_fork();

        if ($processId === 0) {
            try {
                DB::reconnect();

                while (microtime(true) < $startAt) {
                    usleep(100);
                }

                app(ClaimSecret::class)->handle($publicId);
                $outcome = 'claimed';
            } catch (SecretUnavailable) {
                $outcome = 'unavailable';
            } catch (Throwable $exception) {
                $outcome = 'error: '.$exception::class;
            }

            file_put_contents("{$resultsDirectory}/{$worker}", $outcome);

            // Skip PHPUnit shutdown handlers in the child process.
            posix_kill(getmypid(), SIGKILL);
        }

        $processIds[] = $processId;
    }

    foreach ($processIds as $processId) {
        pcntl_waitpid($processId, $status);
    }

    DB::reconnect();

    $files = glob("{$resultsDirectory}/*") ?: [];
    $outcomes = array_count_values(array_map(file_get_contents(...), $files));

    array_map(unlink(...), $files);
    rmdir($resultsDirectory);

    ksort($outcomes);

    return $outcomes;
}

beforeEach(function () {
    if (DB::getDriverName() !== 'pgsql' || ! function_exists('pcntl_fork')) {
        $this->markTestSkipped('Concurrency tests require PostgreSQL and the pcntl extension.');
    }
});

test('exactly one of two parallel claims wins a one-time secret', function () {
    $secret = Secret::factory()->views(1)->create();

    expect(claimConcurrently($secret->public_id, 2))->toBe(['claimed' => 1, 'unavailable' => 1])
        ->and(Secret::query()->whereKey($secret->id)->exists())->toBeFalse();
});

test('exactly three of ten parallel claims win a three-view secret', function () {
    $secret = Secret::factory()->views(3)->create();

    expect(claimConcurrently($secret->public_id, 10))->toBe(['claimed' => 3, 'unavailable' => 7])
        ->and(Secret::query()->whereKey($secret->id)->exists())->toBeFalse();
});

test('parallel claims on a one-time secret never return the envelope twice', function () {
    $secret = Secret::factory()->views(1)->create();

    expect(claimConcurrently($secret->public_id, 10))->toBe(['claimed' => 1, 'unavailable' => 9]);
});
