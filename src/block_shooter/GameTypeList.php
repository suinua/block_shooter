<?php

namespace block_shooter;

use game_chef\models\GameType;

class GameTypeList
{
    private static function getAllTypes(): array
    {
        return [
            strval(self::Solo()),
            strval(self::CorePVP())
        ];
    }

    public static function isExist(GameType $gameType):bool {
        return in_array(strval($gameType), self::getAllTypes());
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
