<?php

namespace App\Http\Controllers;

use App\Enums\ProfileSourceEnum;
use App\Exceptions\ExternalRequestFailedException;
use App\Http\Requests\ProfileLookupRequest;
use App\Services\ProfileService;
use Exception;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

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

            // Specifically handle not found errors
            if ($code === Response::HTTP_NOT_FOUND) {
                return response()->json(['message' => $e->getMessage()], Response::HTTP_NOT_FOUND);
            }

            // Any server errors form the external call can be treated as a 502
            if ($code >= 500) {
                return response()->json(['message' => $e->getMessage()], 502);
            }

            // Any other client errors can be treated as a 400
            if ($code >= 400) {
                return response()->json(['message' => $e->getMessage()], 400);
            }
        } catch (ThrottleRequestsException $e) {
            return response()->json(['message' => 'Too many requests, try again shortly'], Response::HTTP_TOO_MANY_REQUESTS);
        } catch (Exception $e) {
            return response()->json(['message' => 'Server error'], 500);
        }

        return response()->json($profile);
    }
}
