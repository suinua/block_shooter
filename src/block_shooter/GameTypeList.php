<?php

namespace block_shooter;

use game_chef\models\GameType;

class GameTypeList
{
    public static function getAll(): array
    {
        return [
            self::Solo(),
        ];
    }

    public static function Solo(): GameType
    {
        return new GameType("SoloBlockShooter");
    }

    public static function CorePVP(): GameType
    {
        return new GameType("CorePVPBlockShooter");
    }
}
