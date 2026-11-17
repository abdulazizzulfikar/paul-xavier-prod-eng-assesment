<?php

namespace App\Services;

use App\Models\Generation;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class PixelforgeService
{
    // Our generation id is the Idempotency-Key, so retrying a create can never bill twice,
    // and it rides along in metadata so webhooks can be matched before the create call returns.
    public function createGeneration(Generation $generation): Response
    {
        return $this->client()
            ->withHeaders(['Idempotency-Key' => $generation->id])
            ->post('generations', [
                'prompt' => $generation->prompt,
                'size' => $generation->size,
                'quality' => $generation->quality,
                'reference_images' => $generation->reference_images,
                'metadata' => ['generation_id' => $generation->id],
            ]);
    }

    public function getGeneration(string $pixelforgeId): Response
    {
        return $this->client()->get("generations/{$pixelforgeId}");
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(config('pixelforge.base_url'))
            ->withToken(config('pixelforge.api_key'))
            ->acceptJson()
            ->timeout(15);
    }
}
