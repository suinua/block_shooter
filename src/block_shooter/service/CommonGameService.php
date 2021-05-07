<?php

namespace block_shooter\service;

use block_shooter\scoreboard\SoloGameScoreboard;
use game_chef\pmmp\bossbar\Bossbar;
use game_chef\pmmp\hotbar_menu\HotbarMenu;
use game_chef\pmmp\hotbar_menu\HotbarMenuItem;
use pocketmine\entity\Attribute;
use pocketmine\item\ItemIds;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

class CommonGameService
{
    public static function backToLobby(Player $player): void
    {
        $level = Server::getInstance()->getLevelByName("lobby");
        $player->teleport($level->getSpawnLocation());
        $player->getAttributeMap()->getAttribute(Attribute::MOVEMENT_SPEED)->setValue(0.1);
        $player->removeAllEffects();

        $menu = new HotbarMenu($player,[
           new HotbarMenuItem(
               ItemIds::EMERALD,
               0,
               TextFormat::GREEN . "試合に参加",
               function (Player $player) {
                   //todo soloとteamで分ける
                   SoloGameService::randomJoin($player);
               }
           )
        ]);
        $menu->send();

        //ボスバー削除
        foreach (Bossbar::getBossbars($player) as $bossbar) {
            $bossbar->remove();
        }

        //スコアボード削除
        SoloGameScoreboard::delete($player);
    }
}
