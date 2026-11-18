<?php

namespace Tests\Fixtures;

/**
 * Pixelforge payloads copied from the API docs.
 */
final class Pixelforge
{
    public const array RATES = [
        'input_text_tokens' => '5.00',
        'input_image_tokens' => '10.00',
        'output_tokens' => '40.00',
        'reasoning_tokens' => '12.50',
    ];

    /** @return array<string, mixed> */
    public static function pricing(): array
    {
        return [
            'object' => 'pricing',
            'currency' => 'usd',
            'unit' => 'per_1m_tokens',
            'rates' => self::RATES,
            'tokens' => [
                'characters_per_text_token' => 4,
                'text_token_variance' => 0.15,
                'per_reference_image' => 765,
                'output' => [
                    'low' => ['1024x1024' => 280, '1536x1024' => 420, '1024x1536' => 420],
                    'medium' => ['1024x1024' => 1100, '1536x1024' => 1650, '1024x1536' => 1650],
                    'high' => ['1024x1024' => 4300, '1536x1024' => 6450, '1024x1536' => 6450],
                ],
                'reasoning' => [
                    'low' => ['min' => 0, 'max' => 500],
                    'medium' => ['min' => 200, 'max' => 2000],
                    'high' => ['min' => 1000, 'max' => 8000],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function generation(string $localId, string $status = 'queued', array $overrides = []): array
    {
        return array_replace([
            'id' => self::pixelforgeId($localId),
            'object' => 'generation',
            'status' => $status,
            'prompt' => 'A lighthouse on a cliff at dawn, oil painting',
            'size' => '1536x1024',
            'quality' => 'high',
            'reference_images' => ['https://example.com/style.png'],
            'metadata' => ['generation_id' => $localId],
            'image_url' => null,
            'image_url_expires_at' => null,
            'usage' => null,
            'rates' => self::RATES,
            'error' => null,
            'created_at' => '2026-11-04T14:03:11Z',
            'started_at' => null,
            'completed_at' => null,
        ], $overrides);
    }

    /** Pixelforge's id for one of our generations in these fixtures. */
    public static function pixelforgeId(string $localId): string
    {
        return 'gen_'.substr(md5($localId), 0, 26);
    }

    /**
     * Docs example: costs 0.305510000 at the default rates.
     *
     * @return array<string, mixed>
     */
    public static function succeeded(string $localId): array
    {
        return self::generation($localId, 'succeeded', [
            'image_url' => 'https://pixelforge.promptedit.dev/images/'.self::pixelforgeId($localId).'.png?expires=1762351409&signature=abc',
            'image_url_expires_at' => '2026-11-05T14:03:29Z',
            'usage' => [
                'input_text_tokens' => 12,
                'input_image_tokens' => 765,
                'output_tokens' => 6450,
                'reasoning_tokens' => 3184,
                'total_tokens' => 10411,
            ],
            'started_at' => '2026-11-04T14:03:13Z',
            'completed_at' => '2026-11-04T14:03:29Z',
        ]);
    }

    /** @return array<string, mixed> */
    public static function failed(string $localId): array
    {
        return self::generation($localId, 'failed', [
            'error' => [
                'code' => 'internal_error',
                'message' => 'Something went wrong on our side while generating. You were not charged; please retry.',
            ],
            'completed_at' => '2026-11-04T14:03:29Z',
        ]);
    }

    /**
     * @param  array<string, mixed>  $generation
     * @return array<string, mixed>
     */
    public static function event(string $id, array $generation): array
    {
        return [
            'id' => $id,
            'object' => 'event',
            'type' => 'generation.'.$generation['status'],
            'created_at' => '2026-11-04T14:03:29Z',
            'data' => ['object' => $generation],
        ];
    }
}
