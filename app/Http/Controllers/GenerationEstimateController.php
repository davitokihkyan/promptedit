<?php

namespace App\Http\Controllers;

use App\Billing\ChargeEstimator;
use App\Http\Requests\EstimateGenerationRequest;
use App\Http\Resources\ChargeEstimateResource;

final class GenerationEstimateController extends Controller
{
    public function __invoke(EstimateGenerationRequest $request, ChargeEstimator $estimator): ChargeEstimateResource
    {
        return new ChargeEstimateResource($estimator->estimate($request->parameters()));
    }
}
