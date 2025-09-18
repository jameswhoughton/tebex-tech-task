<?php

namespace App\Services\ProfileSourceStrategies;

use App\Enums\ProfileSourceEnum;
use App\Exceptions\ExternalRequestFailedException;
use App\Services\ExternalRequestService;
use App\Services\ProfileSourceInterface;
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

    public function __construct(private ExternalRequestService $requestService) {}

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
        $response = $this->requestService->get($this->getUrl());

        $body = $response->json();

        // The external endpoint appears to return a 200 with a body code of 400 if the Xbox profile cannot be found.
        if (isset($body['error']) && $body['error']['code'] === 400) {
            throw new ExternalRequestFailedException('Unable to find profile', code: 404);
        }

        return [
            'username' => $body['username'],
            'id' => $body['id'],
            'avatar' => $body['meta']['avatar'],
        ];
    }
}
