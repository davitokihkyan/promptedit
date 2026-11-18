<?php

namespace App\Providers;

use App\Pixelforge\HttpPixelforgeClient;
use App\Pixelforge\PixelforgeClient;
use App\Pixelforge\WebhookSignature;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\ServiceProvider;

final class PixelforgeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PixelforgeClient::class, fn (Application $app) => new HttpPixelforgeClient(
            $app->make(Factory::class),
            (string) config('services.pixelforge.base_url'),
            (string) config('services.pixelforge.api_key'),
        ));

        $this->app->bind(WebhookSignature::class, fn () => new WebhookSignature(
            (string) config('services.pixelforge.webhook_secret'),
            (int) config('services.pixelforge.webhook_tolerance'),
        ));
    }
}
