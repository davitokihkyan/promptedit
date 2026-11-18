<?php

namespace App\Http\Controllers;

use App\Generations\HandleWebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class PixelforgeWebhookController extends Controller
{
    public function __invoke(Request $request, HandleWebhookEvent $handler): Response
    {
        $handler->handle($request->json()->all());

        return response()->noContent();
    }
}
