<?php

namespace block_shooter;

use pocketmine\event\Listener;

class MainListener implements Listener {
    public function onJoin(PlayerJoinEvent $event) {
        $pk = new GameRulesChangedPacket();
        $pk->gameRules["doImmediateRespawn"] = [1, true];
        $event->getPlayer()->sendDataPacket($pk);
    }


}