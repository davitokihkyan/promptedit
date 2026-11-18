<?php

namespace App\Http\Resources;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A generation as its owner sees it; our provider cost stays private.
 *
 * @property Generation $resource
 */
final class GenerationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $generation = $this->resource;

        return [
            'id' => $generation->id,
            'status' => $generation->status,
            'prompt' => $generation->prompt,
            'size' => $generation->size,
            'quality' => $generation->quality,
            'reference_images' => $generation->reference_images,
            'estimated_charge' => $generation->estimated_charge,
            'max_charge' => $generation->max_charge,
            'charge' => $generation->charge,
            'image_url' => $generation->imageIsAvailable() ? $generation->image_url : null,
            'image_expired' => $generation->status === GenerationStatus::Succeeded && ! $generation->imageIsAvailable(),
            'failure' => $generation->status === GenerationStatus::Failed ? $this->failureMessage($generation) : null,
            'created_at' => $generation->created_at->toIso8601String(),
            'completed_at' => $generation->completed_at?->toIso8601String(),
        ];
    }

    private function failureMessage(Generation $generation): string
    {
        return match ($generation->error_code) {
            'content_policy_violation' => 'Pixelforge rejected this prompt.',
            'generation_failed' => "The model couldn't produce an image for this prompt.",
            'internal_error' => 'Pixelforge had an internal error. Trying again usually works.',
            'overloaded', 'rate_limit_exceeded', 'concurrency_limit_exceeded' => 'Pixelforge was too busy to take this request. Please try again in a moment.',
            'daily_limit_exceeded' => "Today's generation quota is used up. It resets at midnight UTC.",
            default => $generation->error_message ?? 'Something went wrong.',
        };
    }
}
