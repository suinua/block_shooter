<?php

namespace block_shooter\service;

use game_chef\pmmp\bossbar\Bossbar;
use pocketmine\Player;
use pocketmine\entity\Attribute;
use pocketmine\Server;

class CommonGameService
{
    public static function backToLobby(Player $player): void
    {
        $level = Server::getInstance()->getLevelByName("lobby");
        $player->teleport($level->getSpawnLocation());
        $player->getAttributeMap()->getAttribute(Attribute::MOVEMENT_SPEED)->setValue(0.1);
        $player->removeAllEffects();

        $player->getInventory()->setContents([
            //todo:インベントリセット
        ]);

        //ボスバー削除
        foreach (Bossbar::getBossbars($player) as $bossbar) {
            $bossbar->remove();
        }

        //スコアボード削除
    }
}
