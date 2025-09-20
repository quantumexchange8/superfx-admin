<?php

namespace App\Services\TradingPlatform;

use App\Services\MetaFiveService;
use App\Services\MetaFourService;
use InvalidArgumentException;

class TradingPlatformFactory
{
    public static function make(string $platform): TradingPlatformInterface
    {
        return match (strtolower($platform)) {
            'mt4' => new MetaFourService(),
            'mt5' => new MetaFiveService(),
            default => throw new InvalidArgumentException("Unknown platform: $platform"),
        };
    }
}
