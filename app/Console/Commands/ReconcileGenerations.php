<?php

namespace App\Console\Commands;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use Facades\App\Services\GenerationService;
use Facades\App\Services\PixelforgeService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

// Webhook delivery isn't guaranteed, so poll generations that have gone quiet.
#[Signature('generations:reconcile')]
#[Description('Poll Pixelforge for generations whose webhooks may have been missed')]
class ReconcileGenerations extends Command
{
    public function handle(): void
    {
        $generations = Generation::whereNotNull('pixelforge_id')
            ->whereIn('status', [GenerationStatus::Queued, GenerationStatus::Processing])
            ->where('updated_at', '<', now()->subMinutes(2))
            ->oldest('updated_at')
            ->limit(10) // stay well under Pixelforge's 60 requests per minute
            ->get();

        foreach ($generations as $generation) {
            $response = PixelforgeService::getGeneration($generation->pixelforge_id);

            if ($response->successful()) {
                GenerationService::applyProviderUpdate($generation, $response->json());
            }

            $generation->touch(); // don't re-poll the same row next minute if nothing changed
        }

        $this->info("Checked {$generations->count()} generations.");
    }
}
