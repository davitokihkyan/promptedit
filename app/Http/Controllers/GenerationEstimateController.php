<?php

namespace App\Http\Controllers;

use App\Billing\CostEstimator;
use App\Http\Requests\EstimateGenerationRequest;
use App\Http\Resources\CostEstimateResource;

final class GenerationEstimateController extends Controller
{
    public function __invoke(EstimateGenerationRequest $request, CostEstimator $estimator): CostEstimateResource
    {
        return new CostEstimateResource($estimator->estimate($request->parameters()));
    }
}
