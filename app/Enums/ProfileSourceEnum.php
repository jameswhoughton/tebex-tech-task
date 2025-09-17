<?php

namespace App\Enums;

use App\Services\ProfileSourceInterface;
use App\Services\ProfileSourceStrategies\ProfileSourceMinecraft;
use App\Services\ProfileSourceStrategies\ProfileSourceSteam;

enum ProfileSourceEnum: string
{
    case STEAM = 'steam';
    case XBL = 'xbl';
    case MINECRAFT = 'minecraft';

    public function strategy(): ProfileSourceInterface
    {
        return match ($this) {
            self::STEAM => new (ProfileSourceSteam::class),
            self::MINECRAFT => new (ProfileSourceMinecraft::class),
        };
    }
}
