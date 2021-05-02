<?php

namespace block_shooter\listener;


use block_shooter\GameTypeList;
use block_shooter\usecase\CommonGameUsecase;
use game_chef\pmmp\events\PlayerQuitGameEvent;
use pocketmine\event\Listener;

class CommonGameListener implements Listener
{

    public function onQuitGame(PlayerQuitGameEvent $event)
    {
        $player = $event->getPlayer();
        $gameType = $event->getGameType();
        if (!in_array($gameType, GameTypeList::getAll())) {
            return;
        }

        CommonGameUsecase::backToLobby($player);
    }
}
