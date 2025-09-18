<?php

namespace Tests\Unit\Services;

use App\Services\ExternalRequestService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExternalRequestServiceTest extends TestCase
{
    public function test_get_retires_request(): void
    {
        Http::preventingStrayRequests();

        Http::fake([
            'https://example.com' => Http::sequence()->pushFailedConnection()->pushStatus(200),
        ]);

        $service = new ExternalRequestService();

        $service->get('https://example.com');

        Http::assertSentCount(2);
    }
}
