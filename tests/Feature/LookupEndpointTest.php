<?php

namespace Tests\Feature;

use Tests\TestCase;

class LookupEndpointTest extends TestCase
{
    const ENDPOINT = '/api/lookup';

    public function test_return_invalid_if_type_not_valid(): void
    {
        $this->get(self::ENDPOINT . '?type=aaa')->assertInvalid('type');
    }
}
