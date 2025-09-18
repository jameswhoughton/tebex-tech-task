<?php

namespace App\Interfaces;

interface ProfileSourceInterface
{
    /**
     * Sets the payload data used for the external request, the structure of the
     * payload will vary between services. Any validation of the request parameters
     * should happen at this point. A ValidationException should be thrown if the
     * payload is invalid.
     *
     * @param array<string, any> $payload
     **/
    public function setPayload(array $payload): void;

    /**
     * Returns the key to use when storing the profile data in the cache, the value
     * must be unique across the different profile sources (e.g. steam|{id}).
     **/
    public function getCacheKey(): string;

    /**
     * Performs the external request and returns the formatted profile.
     * If the profile caanot be found, should throw a ProfileNotFoundException.
     * If any other error occurs in the external request, a ProfileRequestException
     * should be thrown.
     *
     * @return array<string, any>
     **/
    public function fetch(): array;
}
