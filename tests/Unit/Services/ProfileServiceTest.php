<?php

namespace Tests\Unit\Services;

use App\Services\ProfileService;
use App\Interfaces\ProfileSourceInterface;
use Exception;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ProfileServiceTest extends TestCase
{
    public function test_fetch_returns_from_cache_if_existis(): void
    {
        $mock = new class() implements ProfileSourceInterface {
            public function getCacheKey(): string
            {
                return '123';
            }

            public function setPayload(array $payload): void {}

            public function fetch(): array
            {
                throw new Exception('attempting to fetch fresh copy of profile');

                return [];
            }
        };

        $fakeProfile = [
            'id' => 123,
            'username' => 'John22',
            'avatar' => 'https://example.com/avatar.png',
        ];

        Cache::put(
            key: '123',
            value: $fakeProfile
        );

        $service = new ProfileService;

        $service->setSource($mock);

        $resp = $service->fetch([]);

        $this->assertEquals($fakeProfile, $resp);
    }

    public function test_fetch_stores_new_profile_in_the_cache(): void
    {
        $mock = new class() implements ProfileSourceInterface {
            public function getCacheKey(): string
            {
                return '123';
            }

            public function setPayload(array $payload): void {}

            public function fetch(): array
            {
                return [
                    'id' => 123,
                    'username' => 'John22',
                    'avatar' => 'https://example.com/avatar.png',
                ];
            }
        };

        Cache::clear();

        $service = new ProfileService;

        $service->setSource($mock);

        $profile = $service->fetch([]);

        $cachedProfile = Cache::get(123);

        $this->assertNotNull($profile);

        $this->assertEquals($profile, $cachedProfile);
    }
}
