<?php

namespace App\Services;

use App\Enums\GenerationStatus;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Facades\App\Services\PricingService;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

class GenerationService
{
    /**
     * @param  array{prompt: string, size: string, quality: string, reference_images?: array<int, string>}  $input
     */
    public function createGeneration(User $user, array $input): Generation
    {
        $referenceImages = array_values($input['reference_images'] ?? []);
        $estimate = PricingService::estimateCharge($input['prompt'], $input['size'], $input['quality'], count($referenceImages));

        $generation = $user->generations()->create([
            'status' => GenerationStatus::Queued,
            'prompt' => $input['prompt'],
            'size' => $input['size'],
            'quality' => $input['quality'],
            'reference_images' => $referenceImages,
            'estimated_min_nano' => $estimate['min'],
            'estimated_max_nano' => $estimate['max'],
        ]);

        SubmitGeneration::dispatch($generation);

        return $generation;
    }

    /**
     * The single place a generation's state and cost change. Called with Pixelforge's
     * generation object from the create response, webhooks and polling alike.
     *
     * @param  array<string, mixed>  $object
     */
    public function applyProviderUpdate(Generation $generation, array $object): Generation
    {
        return DB::transaction(function () use ($generation, $object) {
            $generation = Generation::lockForUpdate()->findOrFail($generation->id);

            $generation->pixelforge_id ??= $object['id'];
            $generation->rates ??= $object['rates'];

            $next = GenerationStatus::from($object['status']);

            if ($generation->status->canMoveTo($next)) {
                $generation->status = $next;

                if ($next === GenerationStatus::Succeeded) {
                    $providerCost = PricingService::calculateProviderCost($object['usage'], $generation->rates);

                    $generation->usage = $object['usage'];
                    $generation->provider_cost_nano = $providerCost;
                    $generation->charged_nano = PricingService::applyMarkup($providerCost);
                    $generation->image_url = $object['image_url'];
                    $generation->completed_at = Date::parse($object['completed_at']);
                }

                if ($next === GenerationStatus::Failed) {
                    $this->fillFailure($generation, $object['error']['code'] ?? 'failed', $object['error']['message'] ?? '', Date::parse($object['completed_at']));
                }
            }

            $generation->save();

            return $generation;
        });
    }

    // For generations Pixelforge never accepted, e.g. a rejected prompt.
    public function markFailed(Generation $generation, string $code, string $message): Generation
    {
        return DB::transaction(function () use ($generation, $code, $message) {
            $generation = Generation::lockForUpdate()->findOrFail($generation->id);

            if (! $generation->status->isFinal()) {
                $generation->status = GenerationStatus::Failed;
                $this->fillFailure($generation, $code, $message, now());
                $generation->save();
            }

            return $generation;
        });
    }

    // Failed generations are never billed.
    private function fillFailure(Generation $generation, string $code, string $message, CarbonImmutable $completedAt): void
    {
        $generation->provider_cost_nano = 0;
        $generation->charged_nano = 0;
        $generation->error_code = $code;
        $generation->error_message = $message;
        $generation->completed_at = $completedAt;
    }
}
