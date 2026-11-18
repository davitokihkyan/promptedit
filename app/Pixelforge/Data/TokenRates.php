<?php

namespace App\Pixelforge\Data;

use App\Money\Usd;

/**
 * Prices in US dollars per 1M tokens.
 */
final readonly class TokenRates
{
    private const int TOKENS_PER_RATE_UNIT = 1_000_000;

    public function __construct(
        public Usd $inputText,
        public Usd $inputImage,
        public Usd $output,
        public Usd $reasoning,
    ) {}

    /** @param array{input_text_tokens: string, input_image_tokens: string, output_tokens: string, reasoning_tokens: string} $rates */
    public static function fromArray(array $rates): self
    {
        return new self(
            inputText: Usd::fromDecimal($rates['input_text_tokens']),
            inputImage: Usd::fromDecimal($rates['input_image_tokens']),
            output: Usd::fromDecimal($rates['output_tokens']),
            reasoning: Usd::fromDecimal($rates['reasoning_tokens']),
        );
    }

    /** Exact in integers: a rate with up to 3 decimals costs a whole number of nanodollars per token. */
    public function costOf(TokenUsage $usage): Usd
    {
        // Rates are per 1M tokens, so sum first and divide once at the end.
        $total = $usage->inputText * $this->inputText->nanos
            + $usage->inputImage * $this->inputImage->nanos
            + $usage->output * $this->output->nanos
            + $usage->reasoning * $this->reasoning->nanos;

        return Usd::ofNanos(intdiv($total, self::TOKENS_PER_RATE_UNIT));
    }

    /** @return array{input_text_tokens: string, input_image_tokens: string, output_tokens: string, reasoning_tokens: string} */
    public function toArray(): array
    {
        return [
            'input_text_tokens' => $this->inputText->toDecimal(),
            'input_image_tokens' => $this->inputImage->toDecimal(),
            'output_tokens' => $this->output->toDecimal(),
            'reasoning_tokens' => $this->reasoning->toDecimal(),
        ];
    }
}
