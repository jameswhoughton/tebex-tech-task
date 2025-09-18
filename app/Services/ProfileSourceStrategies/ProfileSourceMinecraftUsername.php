<?php

namespace App\Services\ProfileSourceStrategies;

use App\Enums\ProfileSourceEnum;
use App\Services\ExternalRequestService;
use App\Interfaces\ProfileSourceInterface;
use Illuminate\Support\Facades\Validator;

class ProfileSourceMinecraftUsername implements ProfileSourceInterface
{
    /**
     * Minecraft username
     **/
    private string $username;

    public function __construct(private ExternalRequestService $requestService)
    {
    }

    public function setPayload(array $payload): void
    {
        Validator::make(
            data: $payload,
            rules: [
                'username' => [
                    'required',
                    'string',
                ],
            ]
        )->validate();

        $this->username = (string)$payload['username'];
    }

    public function getCacheKey(): string
    {
        return sprintf('%s|%s', ProfileSourceEnum::MINECRAFT->value, $this->username);
    }

    public function fetch(): array
    {
        $url = 'https://api.mojang.com/users/profiles/minecraft/' . $this->username;

        $response = $this->requestService->get($url);

        $body = $response->json();

        return [
            'username' => $body['name'],
            'id' => $body['id'],
            'avatar' => "https://crafatar.com/avatars" . $body['id'],
        ];
    }
}
