<?php

namespace Tests\Unit\Services\ProfileSources;

use App\Enums\ProfileSourceEnum;
use App\Exceptions\ExternalRequestFailedException;
use App\Services\ProfileSourceStrategies\ProfileSourceXbl;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProfileSourceXblTest extends TestCase
{
    public static function validationCases(): array
    {
        return [
            'Id missing and username missing' => [
                'payload' => [],
            ],
            'Id and username populated' => [
                'payload' => ['id' => '268641dd8e0b3bf98c902b20da677ab0', 'username' => 'john.smith'],
            ],
        ];
    }

    #[DataProvider('validationCases')]
    public function test_xbl_validation_cases(array $payload): void
    {
        $source = app(ProfileSourceXbl::class);

        $this->expectException(ValidationException::class);

        $source->setPayload($payload);
    }

    public function test_getCacheKey_returns_expected_value(): void
    {
        $source = app(ProfileSourceXbl::class);

        $source->setPayload(['id' => '2533274884045330']);

        $cacheKey = $source->getCacheKey();

        $this->assertEquals(ProfileSourceEnum::XBL->value . '|2533274884045330', $cacheKey);

        $source->setPayload(['username' => 'john.smith']);

        $cacheKey = $source->getCacheKey();

        $this->assertEquals(ProfileSourceEnum::XBL->value . '|john.smith', $cacheKey);
    }

    public function test_fetch_throws_exception_if_profile_not_found(): void
    {
        Http::fake([
            'ident.tebex.io/usernameservices/3/username/*' => Http::response(status: 200, body: ['error' => ['code' => 400]]),
        ]);

        $this->expectException(ExternalRequestFailedException::class);

        $source = app(ProfileSourceXbl::class);

        $source->setPayload(['id' => '2533274884045330']);

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
            'id' => "2533274884045330",
            'created_at' => "2019-06-28 10:22:07",
            'updated_at' => "2025-09-16 10:31:25",
            'cache_expire' => "2025-10-01 10:31:24",
            'username' => "exampleUser123",
            'meta' => [
                'avatar' => "https://example.com/avatar.jpg",
                'avatarfull' => "https://example.com/avatar-full.jpg",
            ],
        ];

        Http::fake([
            'ident.tebex.io/usernameservices/3/username/*' => Http::response(
                body: $fakeResponse,
                status: 200
            ),
        ]);


        $source = app(ProfileSourceXbl::class);

        $source->setPayload(['id' => '2533274884045330']);

        $profile = $source->fetch();

        $this->assertEquals($fakeResponse['username'], $profile['username']);
        $this->assertEquals($fakeResponse['id'], $profile['id']);
        $this->assertEquals($fakeResponse['meta']['avatar'], $profile['avatar']);
    }
}
