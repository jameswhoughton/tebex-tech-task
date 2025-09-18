<?php

namespace Tests\Feature;

use App\Exceptions\ExternalRequestFailedException;
use App\Interfaces\ProfileSerivceInterface;
use App\Interfaces\ProfileSourceInterface;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LookupEndpointTest extends TestCase
{
    const ENDPOINT = '/api/lookup';

    public function test_return_invalid_if_type_not_valid(): void
    {
        $this->getJson(self::ENDPOINT . '?type=aaa')->assertInvalid('type');
    }

    public static function errorCodes(): array
    {
        return [
            'resource not found' => [
                'externalStatusCode' => 404,
                'expectedReturnStatus' => 404,
            ],
            'internal server error' => [
                'externalStatusCode' => 500,
                'expectedReturnStatus' => 502,
            ],
            'unprocessableContent' => [
                'externalStatusCode' => 422,
                'expectedReturnStatus' => 400,
            ],
            'bad request' => [
                'externalStatusCode' => 400,
                'expectedReturnStatus' => 400,
            ],
        ];
    }

    #[DataProvider('errorCodes')]
    public function test_correct_status_code_on_external_request_error(int $externalStatusCode, int $expectedReturnStatus): void
    {
        $testProfileSerivce = new class($externalStatusCode) implements ProfileSerivceInterface
        {
            public function __construct(private int $externalStatusCode) {}

            public function setSource(ProfileSourceInterface $source): void {}

            public function fetch(array $payload): array
            {
                throw new ExternalRequestFailedException($this->externalStatusCode);
            }
        };

        $this->app->instance(ProfileSerivceInterface::class, new ($testProfileSerivce)($externalStatusCode));

        $this->getJson(self::ENDPOINT . '?type=steam')->assertStatus($expectedReturnStatus);
    }

    public function test_expected_output_for_steam(): void
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

        Http::preventingStrayRequests();

        Http::fake([
            'ident.tebex.io/usernameservices/4/username/*' => Http::response(
                body: $fakeResponse,
                status: 200
            ),
        ]);

        $this->getJson(self::ENDPOINT . '?type=steam&id=99999999999999999')
            ->assertOk()
            ->assertJson([
                'id' => $fakeResponse['id'],
                'username' => $fakeResponse['username'],
                'avatar' => $fakeResponse['meta']['avatar'],
            ]);
    }
}
