<?php

namespace App\Http\Controllers;

use App\Enums\ProfileSourceEnum;
use App\Http\Requests\ProfileLookupRequest;
use App\Interfaces\ProfileSerivceInterface;
use Illuminate\Http\JsonResponse;

class ProfileController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(ProfileLookupRequest $request, ProfileSerivceInterface $profileService): JsonResponse
    {
        $profileSource = ProfileSourceEnum::from($request->validated('type'))->strategy($request->all());

        $profileService->setSource($profileSource);

        $profile = $profileService->fetch($request->toArray());

        return response()->json($profile);
    }
}
