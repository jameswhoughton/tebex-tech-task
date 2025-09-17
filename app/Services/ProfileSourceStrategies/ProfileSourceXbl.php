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

class ProfileSourceXbl implements ProfileSourceInterface
{
    /**
     * Xbox live ID
     **/
    private int | null $id;

    /**
     * Xbox live username
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
                    'integer',
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

        return sprintf('%s|%s', ProfileSourceEnum::XBL->value, $identifier);
    }

    private function getUrl(): string
    {
        $identifier = $this->id ?? $this->username;
        $url = 'https://ident.tebex.io/usernameservices/3/username/' . $identifier;

        if ($this->username !== null) {
            $url .= '?type=username';
        }

        return $url;
    }

    public function fetch(): array
    {
        $url = $this->getUrl();

        try {
            $response = Http::timeout(5)
                ->retry(2, 100, throw: false)
                ->get($url);
        } catch (Exception $e) {
            throw new ProfileFetchException('XBL profile service currently unavailable');
        }

        if ($response->failed()) {
            Log::warning('Steam profile request failed', [
                'request' => $url,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new ProfileFetchException('XBL profile service failed with status: ' . $response->status());
        }

        $body = $response->json();

        // The external endpoint appears to return a 200 with a body code of 400 if the steam ID cannot be found.
        if (isset($body['error']) && $body['error']['code'] === 400) {
            throw new ProfileNotFoundException('Unable to find Xbox profile');
        }

        return [
            'username' => $body['username'],
            'id' => $body['id'],
            'avatar' => $body['meta']['avatar'],
        ];
    }
}
