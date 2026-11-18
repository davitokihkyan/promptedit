<?php

namespace Tests\Feature;

use Tests\TestCase;

class TrustedProxiesTest extends TestCase
{
    public function test_forwarded_headers_are_ignored_unless_the_proxy_is_trusted(): void
    {
        $location = $this->withHeaders(['X-Forwarded-Proto' => 'https'])
            ->get(route('generations.index'))
            ->assertRedirect()
            ->headers->get('Location');

        $this->assertStringStartsWith('http://', (string) $location);
    }

    public function test_behind_a_trusted_proxy_redirects_keep_https(): void
    {
        config(['trustedproxy.proxies' => '*']);

        $location = $this->withHeaders(['X-Forwarded-Proto' => 'https'])
            ->get(route('generations.index'))
            ->assertRedirect()
            ->headers->get('Location');

        $this->assertStringStartsWith('https://', (string) $location);
    }
}
