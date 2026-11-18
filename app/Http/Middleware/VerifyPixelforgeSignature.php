<?php

namespace App\Http\Middleware;

use App\Pixelforge\WebhookSignature;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class VerifyPixelforgeSignature
{
    public function __construct(private WebhookSignature $signature) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        // Signed over the raw body, so verify before anything decodes it.
        if (! $this->signature->verify($request->getContent(), (string) $request->header('Pixelforge-Signature'))) {
            return response('Invalid signature.', Response::HTTP_BAD_REQUEST);
        }

        return $next($request);
    }
}
