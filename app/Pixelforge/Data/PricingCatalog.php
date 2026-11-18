<?php

namespace App\Pixelforge\Data;

/**
 * GET /v1/pricing: current rates plus the rules Pixelforge uses to count tokens.
 */
final readonly class PricingCatalog
{
    private const int BASIS_POINTS = 10_000;

    /**
     * @param  array<string, array<string, int>>  $outputTokens  quality => size => tokens
     * @param  array<string, array{min: int, max: int}>  $reasoningTokens  quality => range
     */
    public function __construct(
        public TokenRates $rates,
        private int $charactersPerTextToken,
        private int $textTokenVarianceBasisPoints,
        private int $tokensPerReferenceImage,
        private array $outputTokens,
        private array $reasoningTokens,
    ) {}

    /** @param array<string, mixed> $pricing */
    public static function fromArray(array $pricing): self
    {
        $tokens = $pricing['tokens'];

        return new self(
            rates: TokenRates::fromArray($pricing['rates']),
            charactersPerTextToken: $tokens['characters_per_text_token'],
            textTokenVarianceBasisPoints: (int) round($tokens['text_token_variance'] * self::BASIS_POINTS),
            tokensPerReferenceImage: $tokens['per_reference_image'],
            outputTokens: $tokens['output'],
            reasoningTokens: $tokens['reasoning'],
        );
    }

    /** Our best guess: text tokens at face value, reasoning in the middle of its range. */
    public function expectedUsage(GenerationParameters $parameters): TokenUsage
    {
        $reasoning = $this->reasoningTokens[$parameters->quality->value];

        return new TokenUsage(
            inputText: $this->estimatedTextTokens($parameters->prompt),
            inputImage: $this->imageTokens($parameters),
            output: $this->outputTokens($parameters),
            reasoning: intdiv($reasoning['min'] + $reasoning['max'], 2),
        );
    }

    /** The most Pixelforge can bill: text tokens at +variance, reasoning at its maximum. */
    public function maximumUsage(GenerationParameters $parameters): TokenUsage
    {
        $textTokens = $this->estimatedTextTokens($parameters->prompt);

        return new TokenUsage(
            inputText: $this->divideRoundingUp($textTokens * (self::BASIS_POINTS + $this->textTokenVarianceBasisPoints), self::BASIS_POINTS),
            inputImage: $this->imageTokens($parameters),
            output: $this->outputTokens($parameters),
            reasoning: $this->reasoningTokens[$parameters->quality->value]['max'],
        );
    }

    private function estimatedTextTokens(string $prompt): int
    {
        return $this->divideRoundingUp(mb_strlen($prompt), $this->charactersPerTextToken);
    }

    private function imageTokens(GenerationParameters $parameters): int
    {
        return count($parameters->referenceImages) * $this->tokensPerReferenceImage;
    }

    private function outputTokens(GenerationParameters $parameters): int
    {
        return $this->outputTokens[$parameters->quality->value][$parameters->size->value];
    }

    private function divideRoundingUp(int $dividend, int $divisor): int
    {
        return intdiv($dividend + $divisor - 1, $divisor);
    }
}
