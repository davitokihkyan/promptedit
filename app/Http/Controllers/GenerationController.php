<?php

namespace App\Http\Controllers;

use App\Billing\InsufficientCreditsException;
use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Generations\StartGeneration;
use App\Http\Requests\StoreGenerationRequest;
use App\Http\Resources\GenerationResource;
use App\Models\User;
use App\Pixelforge\PixelforgeException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class GenerationController extends Controller
{
    // Divides evenly into the history grid's 1, 2 and 3 columns.
    private const int PER_PAGE = 24;

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('generations/Index', [
            // Laravel's paginator shape: { data, links, meta }.
            'generations' => GenerationResource::collection($user->generations()->latest()->paginate(self::PER_PAGE)),
            // The form's options and limits come from the same enums and rules that validate it.
            'sizes' => array_column(ImageSize::cases(), 'value'),
            'qualities' => array_column(ImageQuality::cases(), 'value'),
            'maxPromptLength' => StoreGenerationRequest::MAX_PROMPT_LENGTH,
            'maxReferenceImages' => StoreGenerationRequest::MAX_REFERENCE_IMAGES,
        ]);
    }

    public function store(StoreGenerationRequest $request, StartGeneration $start): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $start->handle($user, $request->parameters());
        } catch (InsufficientCreditsException) {
            throw ValidationException::withMessages([
                'credits' => 'Not enough credits: this generation can cost up to the maximum shown. Buy credits to continue.',
            ]);
        } catch (PixelforgeException|ConnectionException) {
            throw ValidationException::withMessages([
                'pixelforge' => "Couldn't load Pixelforge's prices right now. Nothing was charged; please try again.",
            ]);
        }

        return to_route('generations.index');
    }
}
