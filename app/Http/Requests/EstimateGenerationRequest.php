<?php

namespace App\Http\Requests;

/**
 * Prices update while the user types, so a half-written form still gets a price.
 */
final class EstimateGenerationRequest extends StoreGenerationRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'prompt' => ['nullable', 'string', 'max:4000'],
            'reference_images.*' => ['string'],
        ];
    }
}
