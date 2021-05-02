<?php

namespace cafett;

use pocketmine\event\Listener;
use pocketmine\plugin\PluginBase;
use pocketmine\event\player\PlayerJoinEvent;
use block_shooter\scoreboard\SoloGameScoreboard;
use pocketmine\network\mcpe\protocol\GameRulesChangedPacket;

class Main extends PluginBase implements Listener
{
    public function onEnable() {
        SoloGameScoreboard::init();
    }

    public function onJoin(PlayerJoinEvent $event) {
        $pk = new GameRulesChangedPacket();
        $pk->gameRules["doImmediateRespawn"] = [1, true];
        $event->getPlayer()->sendDataPacket($pk);
    }
}
