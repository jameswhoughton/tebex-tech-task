<?php

namespace App\Services\ProfileSourceStrategies;

use App\Enums\ProfileSourceEnum;
use App\Exceptions\ProfileFetchException;
use App\Exceptions\ProfileNotFoundException;
use App\Services\ProfileSourceInterface;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ProfileSourceSteam implements ProfileSourceInterface
{
    /**
     * Steam ID: numeric ID that is 17 digits long.
     **/
    private int $id;

    public function setPayload(array $payload): void
    {
        Validator::make(
            data: $payload,
            rules: [
                'id' => [
                    'required',
                    'integer',
                    'digits:17',
                ],
            ]
        )->validate();

        $this->id = (int)$payload['id'];
    }

    public function getCacheKey(): string
    {
        return sprintf('%s|%d', ProfileSourceEnum::STEAM->value, $this->id);
    }

    public function fetch(): array
    {
        $url = 'https://ident.tebex.io/usernameservices/4/username/' . $this->id;

        try {
            $response = Http::timeout(5)
                ->retry(2, 100, throw: false)
                ->get($url);
        } catch (Exception $e) {
            throw new ProfileFetchException('Steam profile service currently unavailable');
        }

        if ($response->failed()) {
            Log::warning('Steam profile request failed', [
                'request' => $url,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new ProfileFetchException('Steam profile service failed with status: ' . $response->status());
        }

        $body = $response->json();

        // The external endpoint appears to return a 200 with a body code of 400 if the steam ID cannot be found.
        if (isset($body['error']) && $body['error']['code'] === 400) {
            throw new ProfileNotFoundException('Unable to find profile with the Steam ID: ' . $this->id);
        }

        return [
            'username' => $body['username'],
            'id' => $body['id'],
            'avatar' => $body['meta']['avatar'],
        ];
    }
}
