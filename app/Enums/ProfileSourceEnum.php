<?php

namespace App\Enums;

use App\Services\ProfileSourceInterface;
use App\Services\ProfileSourceStrategies\ProfileSourceMinecraftId;
use App\Services\ProfileSourceStrategies\ProfileSourceMinecraftUsername;
use App\Services\ProfileSourceStrategies\ProfileSourceSteam;
use App\Services\ProfileSourceStrategies\ProfileSourceXbl;
use InvalidArgumentException;

enum ProfileSourceEnum: string
{
    case STEAM = 'steam';
    case XBL = 'xbl';
    case MINECRAFT = 'minecraft';

    public function strategy(array $params): ProfileSourceInterface
    {
        return match ($this) {
            self::STEAM => app(ProfileSourceSteam::class),
            self::XBL => app(ProfileSourceXbl::class),
            self::MINECRAFT => $this->minecraftStrategy($params),
        };
    }

    /**
     * Determine which minecraft strategy to use, based upon the request parameters.
     *
     * @param array<string, any> $params
     **/
    private function minecraftStrategy(array $params): ProfileSourceInterface
    {
        if (isset($params['id'])) {
            return app(ProfileSourceMinecraftId::class);
        }

        if (isset($params['username'])) {
            return app(ProfileSourceMinecraftUsername::class);
        }

        throw new InvalidArgumentException('$params must contain either an `id` or a `username`');
    }
}
