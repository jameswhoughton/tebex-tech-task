<?php

namespace App\Services\ProfileSourceStrategies;

use App\Enums\ProfileSourceEnum;
use App\Exceptions\ExternalRequestFailedException;
use App\Services\ExternalRequestService;
use App\Interfaces\ProfileSourceInterface;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class ProfileSourceMinecraftId implements ProfileSourceInterface
{
    /**
     * Minecraft ID: UUIDv3 without hyphens
     **/
    private string $id;

    public function __construct(private ExternalRequestService $requestService)
    {
    }

    public function setPayload(array $payload): void
    {
        Validator::make(
            data: $payload,
            rules: [
                'id' => [
                    'required',
                    'string',
                ],
            ]
        )->validate();

        $this->id = (string)$payload['id'];
    }

    public function getCacheKey(): string
    {
        return sprintf('%s|%s', ProfileSourceEnum::MINECRAFT->value, $this->id);
    }

    public function fetch(): array
    {
        $url = 'https://sessionserver.mojang.com/session/minecraft/profile/' . $this->id;

        $response = $this->requestService->get($url);

        if ($response->status() === 204) {
            throw new ExternalRequestFailedException(Response::HTTP_NOT_FOUND, 'Unable to find profile');
        }

        $body = $response->json();

        return [
            'username' => $body['name'],
            'id' => $body['id'],
            'avatar' => "https://crafatar.com/avatars" . $body['id'],
        ];
    }
}
