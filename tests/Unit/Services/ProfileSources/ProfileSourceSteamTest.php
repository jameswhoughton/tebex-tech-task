<?php

namespace Tests\Unit\Services\ProfileSources;

use App\Enums\ProfileSourceEnum;
use App\Exceptions\ExternalRequestFailedException;
use App\Services\ProfileSourceStrategies\ProfileSourceSteam;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProfileSourceSteamTest extends TestCase
{
    public static function validationCases(): array
    {
        return [
            'Id missing' => [
                'payload' => [],
            ],
            'Id too short' => [
                'payload' => ['id' => '123'],
            ],
            'Id too long' => [
                'payload' => ['id' => Str::repeat('1', 18)],
            ],
            'Non numeric Id' => [
                'payload' => ['id' => Str::repeat('a', 17)],
            ],
        ];
    }

    #[DataProvider('validationCases')]
    public function test_steam_validation_cases(array $payload): void
    {
        $source = app(ProfileSourceSteam::class);

        $this->expectException(ValidationException::class);

        $source->setPayload($payload);
    }

    public function test_getCacheKey_returns_expected_value(): void
    {
        $source = app(ProfileSourceSteam::class);

        $source->setPayload(['id' => '99999999999999999']);

        $cacheKey = $source->getCacheKey();

        $this->assertEquals(ProfileSourceEnum::STEAM->value . '|99999999999999999', $cacheKey);
    }

    public function test_fetch_throws_exception_if_profile_not_found(): void
    {
        Http::fake([
            'ident.tebex.io/usernameservices/4/username/*' => Http::response(status: 200, body: ['error' => ['code' => 400]]),
        ]);

        $this->expectException(ExternalRequestFailedException::class);

        $source = app(ProfileSourceSteam::class);

        $source->setPayload(['id' => '99999999999999999']);

        try {
            $source->fetch();
        } catch (ExternalRequestFailedException $e) {
            $this->assertEquals(Response::HTTP_NOT_FOUND, $e->getStatusCode());

            throw $e;
        }
    }

    public function test_fetch_returns_expected_profile(): void
    {
        $fakeResponse = [
            'id' => "99999999999999999",
            'created_at' => "2019-06-28 10:22:07",
            'updated_at' => "2025-09-16 10:31:25",
            'cache_expire' => "2025-10-01 10:31:24",
            'username' => "exampleUser123",
            'meta' => [
                'avatar' => "https://example.com/avatar.jpg",
                'avatarfull' => "https://example.com/avatar-full.jpg",
                'steamID' => "STEAM_0:1:999999999",
            ],
        ];

        Http::fake([
            'ident.tebex.io/usernameservices/4/username/*' => Http::response(
                body: $fakeResponse,
                status: 200
            ),
        ]);


        $source = app(ProfileSourceSteam::class);

        $source->setPayload(['id' => '99999999999999999']);

        $profile = $source->fetch();

        $this->assertEquals($fakeResponse['username'], $profile['username']);
        $this->assertEquals($fakeResponse['id'], $profile['id']);
        $this->assertEquals($fakeResponse['meta']['avatar'], $profile['avatar']);
    }

    public function test_external_request_should_be_rate_limited(): void
    {
        $fakeResponse = [
            'id' => "99999999999999999",
            'created_at' => "2019-06-28 10:22:07",
            'updated_at' => "2025-09-16 10:31:25",
            'cache_expire' => "2025-10-01 10:31:24",
            'username' => "exampleUser123",
            'meta' => [
                'avatar' => "https://example.com/avatar.jpg",
                'avatarfull' => "https://example.com/avatar-full.jpg",
                'steamID' => "STEAM_0:1:999999999",
            ],
        ];

        Http::fake([
            'ident.tebex.io/usernameservices/4/username/*' => Http::response(
                body: $fakeResponse,
                status: 200
            ),
        ]);

        $source = app(ProfileSourceSteam::class);

        $source->setPayload(['id' => '99999999999999999']);

        // Request should be throttled to 50 requests per minute
        for ($i = 0; $i < 50; $i++) {
            $source->fetch();
        }

        $this->expectException(ThrottleRequestsException::class);

        $source->fetch();
    }
}
