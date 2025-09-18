<?php

namespace App\Services;

use App\Exceptions\ExternalRequestFailedException;
use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExternalRequestService
{
    public static function get(string $url): Response
    {
        try {
            $response = Http::timeout(5)
                ->retry(2, 100, throw: false)
                ->get($url);
        } catch (Exception $e) {
            throw new ExternalRequestFailedException('External service currently unavailable', code: 500);
        }

        if ($response->failed()) {
            Log::warning('External request failed', [
                'request' => $url,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new ExternalRequestFailedException('External request failed with status: ' . $response->status(), code: $response->status());
        }

        return $response;
    }
}
