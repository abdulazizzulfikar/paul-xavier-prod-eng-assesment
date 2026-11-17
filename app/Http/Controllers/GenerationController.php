<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGenerationRequest;
use App\Models\Generation;
use Facades\App\Services\GenerationService;
use Facades\App\Services\PricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GenerationController extends Controller
{
    public function index(Request $request): Response
    {
        $generations = $request->user()->generations()
            ->latest()
            ->paginate(20)
            ->through(fn (Generation $generation) => [
                'id' => $generation->id,
                'status' => $generation->status,
                'prompt' => $generation->prompt,
                'size' => $generation->size,
                'quality' => $generation->quality,
                'reference_images' => $generation->reference_images,
                'estimated_min_nano' => $generation->estimated_min_nano,
                'estimated_max_nano' => $generation->estimated_max_nano,
                'charged_nano' => $generation->charged_nano,
                'image_url' => $generation->image_url,
                'error_message' => $generation->error_message,
                'created_at' => $generation->created_at,
                'completed_at' => $generation->completed_at,
            ]);

        return Inertia::render('generations/Index', [
            'generations' => $generations,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('generations/Create');
    }

    public function estimate(Request $request): JsonResponse
    {
        $input = $request->validate([
            'prompt' => ['nullable', 'string', 'max:4000'],
            'size' => ['required', 'in:1024x1024,1536x1024,1024x1536'],
            'quality' => ['required', 'in:low,medium,high'],
            'reference_image_count' => ['required', 'integer', 'between:0,4'],
        ]);

        return response()->json(PricingService::estimateCharge(
            $input['prompt'] ?? '',
            $input['size'],
            $input['quality'],
            (int) $input['reference_image_count'],
        ));
    }

    public function store(StoreGenerationRequest $request): RedirectResponse
    {
        GenerationService::createGeneration($request->user(), $request->generationInput());

        return to_route('generations.index');
    }
}
