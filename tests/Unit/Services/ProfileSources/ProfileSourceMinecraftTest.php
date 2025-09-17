<?php

namespace Tests\Unit\Services\ProfileSources;

use App\Enums\ProfileSourceEnum;
use App\Exceptions\ProfileNotFoundException;
use App\Services\ProfileSourceStrategies\ProfileSourceMinecraft;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProfileSourceMinecraftTest extends TestCase
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
    public function test_minecraft_validation_cases(array $payload): void
    {
        $source = new ProfileSourceMinecraft;

        $this->expectException(ValidationException::class);

        $source->setPayload($payload);
    }

    public function test_getCacheKey_returns_expected_value(): void
    {
        $source = new ProfileSourceMinecraft;

        $source->setPayload(['id' => '268641dd8e0b3bf98c902b20da677ab0']);

        $cacheKey = $source->getCacheKey();

        $this->assertEquals(ProfileSourceEnum::MINECRAFT->value . '|268641dd8e0b3bf98c902b20da677ab0', $cacheKey);
    }

    public function test_fetch_with_id_throws_exception_if_profile_not_found(): void
    {
        Http::preventStrayRequests();

        Http::fake([
            'https://sessionserver.mojang.com/*' => 204,
        ]);

        $this->expectException(ProfileNotFoundException::class);

        $source = new ProfileSourceMinecraft;

        $source->setPayload(['id' => '268641dd8e0b3bf98c902b20da677ab0']);

        $source->fetch();
    }

    public function test_fetch_with_username_throws_exception_if_profile_not_found(): void
    {
        $this->withoutExceptionHandling();
        Http::preventStrayRequests();

        Http::fake([
            'https://api.mojang.com/*' => Http::response('Not Found', 404),
        ]);

        $this->expectException(ProfileNotFoundException::class);

        $source = new ProfileSourceMinecraft;

        $source->setPayload(['username' => 'John']);

        $source->fetch();
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


        $source = new ProfileSourceMinecraft;

        $source->setPayload(['id' => '268641dd8e0b3bf98c902b20da677ab0']);

        $profile = $source->fetch();

        $this->assertEquals($fakeResponse['name'], $profile['username']);
        $this->assertEquals($fakeResponse['id'], $profile['id']);
        $this->assertEquals("https://crafatar.com/avatars" . $fakeResponse['id'], $profile['avatar']);
    }
}
