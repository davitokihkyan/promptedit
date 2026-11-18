<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_sends_visitors_to_the_studio()
    {
        $response = $this->get(route('home'));

        $response->assertRedirect(route('generations.index'));
    }
}
