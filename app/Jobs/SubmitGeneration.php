<?php

namespace App\Jobs;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use DateTimeInterface;
use Facades\App\Services\GenerationService;
use Facades\App\Services\PixelforgeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Throwable;

class SubmitGeneration implements ShouldQueue
{
    use Queueable;

    // Errors (timeouts, 5xx) are retried at most this many times.
    public int $maxExceptions = 5;

    public function __construct(public Generation $generation) {}

    // Waiting for one of Pixelforge's 3 concurrent slots can take a while, so
    // releases are bounded by time rather than by count.
    public function retryUntil(): DateTimeInterface
    {
        return now()->addMinutes(30);
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [5, 15, 30, 60];
    }

    public function handle(): void
    {
        $generation = $this->generation->fresh();

        if ($generation->status !== GenerationStatus::Queued || $generation->pixelforge_id) {
            return;
        }

        // Retries reuse the same Idempotency-Key, so a create that succeeded but timed out is not repeated.
        $response = PixelforgeService::createGeneration($generation);

        if ($response->successful()) {
            GenerationService::applyProviderUpdate($generation, $response->json());

            return;
        }

        $code = $response->json('error.code');
        $message = $response->json('error.message', 'Pixelforge rejected the request.');

        // Waiting a few seconds won't help once the UTC day's quota is spent.
        if ($code === 'daily_limit_exceeded') {
            GenerationService::markFailed($generation, $code, 'Daily generation limit reached. Please try again tomorrow (UTC).');

            return;
        }

        // Rate, concurrency and overload limits: nothing was created, wait as told and try again.
        if (in_array($response->status(), [429, 503])) {
            $this->release((int) $response->header('Retry-After') ?: 5);

            return;
        }

        // Rejected prompt, invalid input or a bad key: retrying gives the same answer.
        if ($response->clientError()) {
            GenerationService::markFailed($generation, $code ?? 'rejected', $message);

            return;
        }

        throw new RuntimeException("Pixelforge returned {$response->status()} for generation {$generation->id}.");
    }

    public function failed(?Throwable $exception): void
    {
        GenerationService::markFailed($this->generation, 'submit_failed', 'We could not reach Pixelforge. You were not charged.');
    }
}
