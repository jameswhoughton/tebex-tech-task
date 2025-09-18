<?php

namespace App\Http\Controllers;

use App\Enums\ProfileSourceEnum;
use App\Exceptions\ExternalRequestFailedException;
use App\Http\Requests\ProfileLookupRequest;
use App\Services\ProfileService;
use Exception;
use Illuminate\Http\JsonResponse;

class ProfileController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(ProfileLookupRequest $request, ProfileService $profileService): JsonResponse
    {
        try {
            $profileSource = ProfileSourceEnum::from($request->validated('type'))->strategy($request->all());

            $profileService->setSource($profileSource);

            $profile = $profileService->fetch($request->toArray());
        } catch (ExternalRequestFailedException $e) {

            $code = $e->getCode();

            // Specifically andle not found errors
            if ($code === 404) {
                return response()->json(['message' => $e->getMessage()], 404);
            }

            // Any server errors form the external call can be treated as a 502
            if ($code >= 500) {
                return response()->json(['message' => $e->getMessage()], 502);
            }

            // Any other client errors can be treated as a 400
            if ($code >= 400) {
                return response()->json(['message' => $e->getMessage()], 400);
            }
        } catch (Exception $e) {
            return response()->json(['message' => 'Server error'], 500);
        }

        return response()->json($profile);
    }
}
