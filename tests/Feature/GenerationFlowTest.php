<?php

use App\Enums\GenerationStatus;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Models\User;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config(['pixelforge.webhook_secret' => 'whsec_test', 'pixelforge.base_url' => 'https://pixelforge.test/v1']);
});

function makeGeneration(array $attributes = []): Generation
{
    return User::factory()->create()->generations()->create(array_merge([
        'status' => GenerationStatus::Queued,
        'prompt' => 'A lighthouse',
        'size' => '1024x1024',
        'quality' => 'medium',
        'reference_images' => [],
        'estimated_min_nano' => 1,
        'estimated_max_nano' => 2,
    ], $attributes));
}

function providerObject(Generation $generation, string $status, array $overrides = []): array
{
    return array_merge([
        'id' => 'gen_123',
        'object' => 'generation',
        'status' => $status,
        'metadata' => ['generation_id' => $generation->id],
        'image_url' => $status === 'succeeded' ? 'https://pixelforge.test/images/gen_123.png' : null,
        'usage' => $status === 'succeeded'
            ? ['input_text_tokens' => 41, 'input_image_tokens' => 0, 'output_tokens' => 3300, 'reasoning_tokens' => 2950, 'total_tokens' => 6291]
            : null,
        'rates' => ['input_text_tokens' => '5.00', 'input_image_tokens' => '10.00', 'output_tokens' => '40.00', 'reasoning_tokens' => '12.50'],
        'error' => $status === 'failed' ? ['code' => 'generation_failed', 'message' => 'Could not draw it.'] : null,
        'completed_at' => in_array($status, ['succeeded', 'failed']) ? '2026-11-04T23:59:59Z' : null,
    ], $overrides);
}

function sendWebhook(string $eventId, array $object, ?string $secret = 'whsec_test')
{
    $body = json_encode([
        'id' => $eventId,
        'object' => 'event',
        'type' => 'generation.'.$object['status'],
        'data' => ['object' => $object],
    ]);
    $timestamp = time();
    $signature = hash_hmac('sha256', "{$timestamp}.{$body}", $secret);

    return test()->call('POST', '/webhooks/pixelforge', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_PIXELFORGE_SIGNATURE' => "t={$timestamp},v1={$signature}",
    ], $body);
}

it('stores a queued generation with its estimate and queues the submission', function () {
    Queue::fake();
    $user = User::factory()->create();

    $this->actingAs($user)->post('/generations', [
        'prompt' => str_repeat('a', 400),
        'size' => '1536x1024',
        'quality' => 'high',
        'reference_images' => ['https://example.com/a.png', 'https://example.com/b.png'],
    ])->assertRedirect('/generations');

    $generation = $user->generations()->sole();
    expect($generation->status)->toBe(GenerationStatus::Queued)
        ->and($generation->estimated_max_nano)->toBe(467_343_750);
    Queue::assertPushed(SubmitGeneration::class);
});

it('submits with our id as idempotency key and locks the returned rates', function () {
    $generation = makeGeneration();
    Http::fake(['*/generations' => Http::response(providerObject($generation, 'queued'), 202)]);

    SubmitGeneration::dispatchSync($generation);

    Http::assertSent(fn ($request) => $request->header('Idempotency-Key')[0] === $generation->id
        && $request['metadata']['generation_id'] === $generation->id);
    expect($generation->fresh())
        ->pixelforge_id->toBe('gen_123')
        ->rates->toHaveKey('reasoning_tokens', '12.50');
});

it('fails a blocked prompt immediately and for free', function () {
    $generation = makeGeneration();
    Http::fake(['*' => Http::response(['error' => ['code' => 'content_policy_violation', 'message' => 'The prompt was rejected.']], 400)]);

    SubmitGeneration::dispatchSync($generation);

    expect($generation->fresh())
        ->status->toBe(GenerationStatus::Failed)
        ->charged_nano->toBe(0)
        ->error_code->toBe('content_policy_violation');
});

it('keeps an overloaded submission queued for a retry', function () {
    $generation = makeGeneration();
    Http::fake(['*' => Http::response(['error' => ['code' => 'overloaded']], 503, ['Retry-After' => '2'])]);

    SubmitGeneration::dispatchSync($generation);

    expect($generation->fresh()->status)->toBe(GenerationStatus::Queued);
});

it('waits at least as long as Retry-After before retrying', function () {
    $generation = makeGeneration();
    Http::fake(['*' => Http::response(['error' => ['code' => 'rate_limit_exceeded']], 429, ['Retry-After' => '30'])]);

    $job = (new SubmitGeneration($generation))->withFakeQueueInteractions();
    $job->handle();

    $job->assertReleased(delay: 30);
});

it('rejects webhooks with a bad signature', function () {
    $generation = makeGeneration();

    sendWebhook('evt_1', providerObject($generation, 'succeeded'), 'whsec_wrong')->assertStatus(400);

    expect(WebhookEvent::count())->toBe(0);
});

it('records the cost of a succeeded generation exactly once, even when delivered twice', function () {
    $generation = makeGeneration(['pixelforge_id' => 'gen_123']);
    $object = providerObject($generation, 'succeeded');

    sendWebhook('evt_1', $object)->assertNoContent();
    sendWebhook('evt_1', $object)->assertNoContent();

    expect(WebhookEvent::count())->toBe(1)
        ->and($generation->fresh())
        ->status->toBe(GenerationStatus::Succeeded)
        ->provider_cost_nano->toBe(169_080_000)
        ->charged_nano->toBe(211_350_000)
        ->completed_at->toDateTimeString()->toBe('2026-11-04 23:59:59');
});

it('never moves a finished generation backwards', function () {
    $generation = makeGeneration(['pixelforge_id' => 'gen_123']);

    sendWebhook('evt_2', providerObject($generation, 'succeeded'));
    sendWebhook('evt_1', providerObject($generation, 'processing'));
    sendWebhook('evt_3', providerObject($generation, 'failed'));

    expect($generation->fresh())
        ->status->toBe(GenerationStatus::Succeeded)
        ->charged_nano->toBe(211_350_000);
});

it('matches a webhook that arrives before the create call returned', function () {
    $generation = makeGeneration();

    sendWebhook('evt_1', providerObject($generation, 'succeeded'))->assertNoContent();

    Http::fake(['*/generations' => Http::response(providerObject($generation, 'queued'), 202)]);
    SubmitGeneration::dispatchSync($generation->fresh());

    expect($generation->fresh())
        ->pixelforge_id->toBe('gen_123')
        ->status->toBe(GenerationStatus::Succeeded)
        ->charged_nano->toBe(211_350_000);
});

it('charges nothing for a generation that failed while processing', function () {
    $generation = makeGeneration(['pixelforge_id' => 'gen_123']);

    sendWebhook('evt_1', providerObject($generation, 'failed'));

    expect($generation->fresh())
        ->status->toBe(GenerationStatus::Failed)
        ->charged_nano->toBe(0)
        ->provider_cost_nano->toBe(0)
        ->error_message->toBe('Could not draw it.');
});

it('settles missed webhooks by polling', function () {
    $generation = makeGeneration(['pixelforge_id' => 'gen_123', 'status' => GenerationStatus::Processing]);
    Generation::whereKey($generation->id)->update(['updated_at' => now()->subMinutes(5)]);
    Http::fake(['*/generations/gen_123' => Http::response(providerObject($generation, 'succeeded'))]);

    $this->artisan('generations:reconcile')->assertSuccessful();

    expect($generation->fresh())
        ->status->toBe(GenerationStatus::Succeeded)
        ->charged_nano->toBe(211_350_000);
});
