<?php

namespace App\Services\ProfileSourceStrategies;

use App\Enums\ProfileSourceEnum;
use App\Exceptions\ExternalRequestFailedException;
use App\Services\ExternalRequestService;
use App\Services\ProfileSourceInterface;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;

class ProfileSourceSteam implements ProfileSourceInterface
{
    /**
     * Steam ID: numeric ID that is 17 digits long.
     **/
    private int $id;

    public function __construct(private ExternalRequestService $requestService) {}

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

        $ip = request()->ip();

        $response = RateLimiter::attempt(
            key: sprintf('external_call|%s|%s', ProfileSourceEnum::STEAM->value, $ip),
            maxAttempts: 50,
            callback: fn() => $this->requestService->get($url),
            decaySeconds: 60
        );

        if ($response === false) {
            throw new ThrottleRequestsException;
        }

        $body = $response->json();

        // The external endpoint appears to return a 200 with a body code of 400 if the steam ID cannot be found.
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
