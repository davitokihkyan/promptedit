<?php

namespace Tests\Unit\Pixelforge;

use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Pixelforge\Data\GenerationParameters;
use App\Pixelforge\Data\PricingCatalog;
use App\Pixelforge\Data\TokenRates;
use App\Pixelforge\Data\TokenUsage;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\Pixelforge;

class PricingCatalogTest extends TestCase
{
    public function test_maximum_matches_the_worked_example_in_the_docs(): void
    {
        $catalog = PricingCatalog::fromArray(Pixelforge::pricing());

        $usage = $catalog->maximumUsage($this->docsExample());

        $this->assertEquals(new TokenUsage(inputText: 115, inputImage: 1530, output: 6450, reasoning: 8000), $usage);
        $this->assertSame('0.373875000', $catalog->rates->costOf($usage)->toDecimal());
    }

    public function test_expected_usage_takes_text_at_face_value_and_reasoning_mid_range(): void
    {
        $usage = PricingCatalog::fromArray(Pixelforge::pricing())->expectedUsage($this->docsExample());

        $this->assertEquals(new TokenUsage(inputText: 100, inputImage: 1530, output: 6450, reasoning: 4500), $usage);
    }

    public function test_text_tokens_count_unicode_characters_not_bytes(): void
    {
        $prompt = str_repeat('🌅', 8); // 32 bytes, 8 characters

        $usage = PricingCatalog::fromArray(Pixelforge::pricing())
            ->expectedUsage(new GenerationParameters($prompt, ImageSize::Square, ImageQuality::Low));

        $this->assertSame(2, $usage->inputText);
    }

    public function test_cost_matches_the_usage_report_example_in_the_docs(): void
    {
        $usage = new TokenUsage(inputText: 41, inputImage: 0, output: 3300, reasoning: 2950);

        $this->assertSame('0.169080000', TokenRates::fromArray(Pixelforge::RATES)->costOf($usage)->toDecimal());
    }

    private function docsExample(): GenerationParameters
    {
        return new GenerationParameters(
            prompt: str_repeat('a', 400),
            size: ImageSize::Landscape,
            quality: ImageQuality::High,
            referenceImages: ['https://example.com/a.png', 'https://example.com/b.png'],
        );
    }
}
