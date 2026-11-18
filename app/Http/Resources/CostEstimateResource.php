<?php

namespace App\Http\Resources;

use App\Billing\CostEstimate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property CostEstimate $resource
 */
final class CostEstimateResource extends JsonResource
{
    public static $wrap = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'expected' => $this->resource->expected,
            'maximum' => $this->resource->maximum,
            'tokens' => [
                'expected' => $this->resource->expectedUsage->toArray(),
                'maximum' => $this->resource->maximumUsage->toArray(),
            ],
        ];
    }
}
