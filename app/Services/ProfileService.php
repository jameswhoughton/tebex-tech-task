<?php

namespace App\Services;

use App\Interfaces\ProfileSerivceInterface;
use App\Interfaces\ProfileSourceInterface;
use Illuminate\Support\Facades\Cache;

class ProfileService implements ProfileSerivceInterface
{
    private ProfileSourceInterface $source;

    /**
     * Set the source to use to fetch profile data (e.g. steam, xbl, minecraft etc.).
     **/
    public function setSource(ProfileSourceInterface $source): void
    {
        $this->source = $source;
    }

    /**
     * Use the provided payload to fetch the profile data from either the cache, or the chosen source.
     *
     * @param array<string, any> $payload
     * @return array<string, any>
     **/
    public function fetch(array $payload): array
    {
        $this->source->setPayload($payload);

        // Check cache
        $profile = Cache::get($this->source->getCacheKey());

        if ($profile !== null) {
            return $profile;
        }

        $profile = $this->source->fetch();

        // Store to cache
        Cache::put($this->source->getCacheKey(), $profile, now()->addDay());

        return $profile;
    }
}
