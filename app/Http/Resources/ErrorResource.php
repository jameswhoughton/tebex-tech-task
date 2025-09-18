<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ErrorResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'error' => [
                'message' => $this->resource['message'] ?? 'Unexpected error',
                'details' => $this->resource['details'] ?? null,
            ]
        ];
    }
}
