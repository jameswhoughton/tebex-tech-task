<?php

namespace App\Services\ProfileSourceStrategies;

use App\Enums\ProfileSourceEnum;
use App\Exceptions\ProfileFetchException;
use App\Exceptions\ProfileNotFoundException;
use App\Services\ProfileSourceInterface;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ProfileSourceMinecraft implements ProfileSourceInterface
{
    /**
     * Minecraft ID: Can either be the Xbox User ID (numeric) or a UUIDv3
     **/
    private string | null $id;

    /**
     * Minecraft username
     **/
    private string | null $username;

    public function setPayload(array $payload): void
    {
        Validator::make(
            data: $payload,
            rules: [
                'id' => [
                    'required_without:username',
                    'prohibits:username',
                    'string',
                ],
                'username' => [
                    'required_without:id',
                    'prohibits:id',
                    'string',
                ],
            ]
        )->validate();

        $this->id = $payload['id'] ?? null;
        $this->username = $payload['username'] ?? null;
    }

    public function getCacheKey(): string
    {
        $identifier = $this->id ?? $this->username;

        return sprintf('%s|%s', ProfileSourceEnum::MINECRAFT->value, $identifier);
    }

    private function getUrl(): string
    {
        if ($this->id !== null) {
            return 'https://sessionserver.mojang.com/session/minecraft/profile/' . $this->id;
        }

        return 'https://api.mojang.com/users/profiles/minecraft/' . $this->username;
    }

    public function fetch(): array
    {
        try {
            $url = $this->getUrl();
            $response = Http::timeout(5)
                ->retry(2, 100, throw: false)
                ->get($url);
        } catch (Exception $e) {
            throw new ProfileFetchException('Minecraft profile service currently unavailable');
        }

        if ($response->status() === 204) {
            throw new ProfileNotFoundException('Unable to find profile with the Minecraft ID: ' . $this->id);
        }

        if ($response->status() === 404) {
            throw new ProfileNotFoundException('Unable to find profile with the Minecraft username: ' . $this->username);
        }

        if ($response->failed()) {
            Log::warning('Minecraft profile request failed', [
                'request' => $url,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new ProfileFetchException('Minecraft profile service failed with status: ' . $response->status());
        }

        $body = $response->json();

        return [
            'username' => $body['name'],
            'id' => $body['id'],
            'avatar' => "https://crafatar.com/avatars" . $body['id'],
        ];
    }
}
