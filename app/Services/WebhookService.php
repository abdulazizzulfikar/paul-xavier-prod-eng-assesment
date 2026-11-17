<?php

namespace App\Services;

use App\Models\Generation;
use App\Models\WebhookEvent;
use Facades\App\Services\GenerationService;
use Illuminate\Support\Facades\DB;

class WebhookService
{
    // Header format: t=<unix timestamp>,v1=<hex HMAC-SHA256 of "<timestamp>.<raw body>">.
    public function verifySignature(string $rawBody, ?string $header): bool
    {
        $parts = [];

        foreach (explode(',', (string) $header) as $part) {
            [$key, $value] = array_pad(explode('=', $part, 2), 2, '');
            $parts[$key] = $value;
        }

        $timestamp = (int) ($parts['t'] ?? 0);
        $expected = hash_hmac('sha256', "{$timestamp}.{$rawBody}", (string) config('pixelforge.webhook_secret'));

        return hash_equals($expected, $parts['v1'] ?? '') && abs(time() - $timestamp) <= 300;
    }

    /**
     * Records an event once (unique event_id), then applies it. Safe to call again with the
     * same event: an already processed event is skipped, and the update itself only moves forward.
     *
     * @param  array<string, mixed>  $event
     */
    public function handleEvent(array $event): void
    {
        $webhookEvent = WebhookEvent::createOrFirst(
            ['event_id' => $event['id']],
            ['type' => $event['type'], 'payload' => $event],
        );

        if ($webhookEvent->processed_at) {
            return;
        }

        DB::transaction(function () use ($webhookEvent, $event) {
            $object = $event['data']['object'] ?? null;
            $generation = str_starts_with($event['type'], 'generation.') ? $this->findGeneration($object) : null;

            if ($generation) {
                GenerationService::applyProviderUpdate($generation, $object);
            }

            $webhookEvent->update(['processed_at' => now()]);
        });
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function findGeneration(array $object): ?Generation
    {
        $ourId = $object['metadata']['generation_id'] ?? null;

        return (is_string($ourId) ? Generation::find($ourId) : null)
            ?? Generation::firstWhere('pixelforge_id', $object['id']);
    }
}
