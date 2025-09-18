<?php

namespace Tests\Unit\Services\ProfileSources;

use App\Enums\ProfileSourceEnum;
use App\Exceptions\ExternalRequestFailedException;
use App\Services\ProfileSourceStrategies\ProfileSourceMinecraftId;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProfileSourceMinecraftIdTest extends TestCase
{
    public static function validationCases(): array
    {
        return [
            'Id missing' => [
                'payload' => [],
            ],
        ];
    }

    #[DataProvider('validationCases')]
    public function test_minecraft_validation_cases(array $payload): void
    {
        $source = app(ProfileSourceMinecraftId::class);

        $this->expectException(ValidationException::class);

        $source->setPayload($payload);
    }

    public function test_getCacheKey_returns_expected_value(): void
    {
        $source = app(ProfileSourceMinecraftId::class);

        $source->setPayload(['id' => '268641dd8e0b3bf98c902b20da677ab0']);

        $cacheKey = $source->getCacheKey();

        $this->assertEquals(ProfileSourceEnum::MINECRAFT->value . '|268641dd8e0b3bf98c902b20da677ab0', $cacheKey);
    }

    public function test_fetch_throws_exception_if_profile_not_found(): void
    {
        Http::preventStrayRequests();

        Http::fake([
            'https://sessionserver.mojang.com/*' => 204,
        ]);

        $this->expectException(ExternalRequestFailedException::class);

        $source = app(ProfileSourceMinecraftId::class);

        $source->setPayload(['id' => '268641dd8e0b3bf98c902b20da677ab0']);

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
            'https://sessionserver.mojang.com/session/minecraft/profile/*' => Http::response(
                body: $fakeResponse,
                status: 200
            ),
        ]);


        $source = app(ProfileSourceMinecraftId::class);

        $source->setPayload(['id' => '268641dd8e0b3bf98c902b20da677ab0']);

        $profile = $source->fetch();

        $this->assertEquals($fakeResponse['name'], $profile['username']);
        $this->assertEquals($fakeResponse['id'], $profile['id']);
        $this->assertEquals("https://crafatar.com/avatars" . $fakeResponse['id'], $profile['avatar']);
    }
}
