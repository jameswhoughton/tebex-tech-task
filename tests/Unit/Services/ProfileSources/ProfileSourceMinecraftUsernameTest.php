<?php

namespace Tests\Unit\Services\ProfileSources;

use App\Enums\ProfileSourceEnum;
use App\Exceptions\ExternalRequestFailedException;
use App\Services\ProfileSourceStrategies\ProfileSourceMinecraftUsername;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProfileSourceMinecraftUsernameTest extends TestCase
{
    public static function validationCases(): array
    {
        return [
            'username missing' => [
                'payload' => [],
            ],
        ];
    }

    #[DataProvider('validationCases')]
    public function test_minecraft_validation_cases(array $payload): void
    {
        $source = app(ProfileSourceMinecraftUsername::class);

        $this->expectException(ValidationException::class);

        $source->setPayload($payload);
    }

    public function test_getCacheKey_returns_expected_value(): void
    {
        $source = app(ProfileSourceMinecraftUsername::class);

        $source->setPayload(['username' => 'AAABBB1']);

        $cacheKey = $source->getCacheKey();

        $this->assertEquals(ProfileSourceEnum::MINECRAFT->value . '|AAABBB1', $cacheKey);
    }

    public function test_fetch_with_username_throws_exception_if_profile_not_found(): void
    {
        $this->withoutExceptionHandling();
        Http::preventStrayRequests();

        Http::fake([
            'https://api.mojang.com/*' => Http::response('Not Found', 404),
        ]);

        $this->expectException(ExternalRequestFailedException::class);

        $source = app(ProfileSourceMinecraftUsername::class);

        $source->setPayload(['username' => 'John']);

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
            'id' => "268641dd8e0b3bf98c902b20da677ab0",
            'name' => "john.smith",
            'properties' => [
                [
                    'name' => 'textures',
                    'value' => 'abc',
                ],
            ],
            'profileActions' => [],
        ];
        Http::preventStrayRequests();

        Http::fake([
            'https://api.mojang.com/*' => Http::response(
                body: $fakeResponse,
                status: 200
            ),
        ]);


        $source = app(ProfileSourceMinecraftUsername::class);

        $source->setPayload(['username' => 'john.smith']);

        $profile = $source->fetch();

        $this->assertEquals($fakeResponse['name'], $profile['username']);
        $this->assertEquals($fakeResponse['id'], $profile['id']);
        $this->assertEquals("https://crafatar.com/avatars" . $fakeResponse['id'], $profile['avatar']);
    }
}
