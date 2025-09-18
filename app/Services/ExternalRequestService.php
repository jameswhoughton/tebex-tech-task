<?php

namespace App\Services;

use App\Exceptions\ExternalRequestFailedException;
use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExternalRequestService
{
    public static function get(string $url): Response
    {
        try {
            $response = Http::timeout(5)
                ->retry(
                    times: 3,
                    sleepMilliseconds: fn (int $attempt) => $attempt * 100,
                    throw: false
                )
                ->get($url);
        } catch (Exception $e) {
            Log::warning('External service unavailable', [
                'request' => $url,
                'error_code' => $e->getCode(),
                'message' => $e->getMessage(),
            ]);

            throw new ExternalRequestFailedException(HttpResponse::HTTP_INTERNAL_SERVER_ERROR, 'External service currently unavailable');
        }

        if ($response->failed()) {
            Log::warning('External request failed', [
                'request' => $url,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new ExternalRequestFailedException($response->status(), 'External request failed');
        }

        return $response;
    }
}
