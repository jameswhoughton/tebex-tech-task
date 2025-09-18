<?php

namespace App\Interfaces;

use App\Interfaces\ProfileSourceInterface;

interface ProfileSerivceInterface
{
    /**
     * Set the source to use to fetch profile data (e.g. steam, xbl, minecraft etc.).
     **/
    public function setSource(ProfileSourceInterface $source): void;

    /**
     * Use the provided payload to fetch the profile data from either the cache, or the chosen source.
     *
     * @param array<string, any> $payload
     * @return array<string, any>
     **/
    public function fetch(array $payload): array;
}
