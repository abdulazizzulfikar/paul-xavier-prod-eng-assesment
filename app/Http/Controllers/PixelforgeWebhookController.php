<?php

namespace App\Http\Controllers;

use Facades\App\Services\WebhookService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PixelforgeWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        // Verify the raw body before anything decodes it.
        $rawBody = $request->getContent();

        abort_unless(
            WebhookService::verifySignature($rawBody, $request->header('Pixelforge-Signature')),
            400,
            'Invalid Pixelforge signature.',
        );

        // If handling throws, the 500 makes Pixelforge retry; the event stays unprocessed until then.
        WebhookService::handleEvent(json_decode($rawBody, true));

        return response()->noContent();
    }
}
