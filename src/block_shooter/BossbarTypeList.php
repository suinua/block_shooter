<?php

namespace block_shooter;

use game_chef\models\GameType;
use game_chef\pmmp\bossbar\BossbarType;

class BossbarTypeList
{

    public static function fromGameType(GameType $gameType): BossbarType
    {
        return new BossbarType(strval($gameType));
    }

    public static function Solo(): BossbarType
    {
        return new BossbarType("SoloBlockShooter");
    }

    public static function CorePVP(): BossbarType
    {
        return new BossbarType("CorePVPBlockShooter");
    }
}
