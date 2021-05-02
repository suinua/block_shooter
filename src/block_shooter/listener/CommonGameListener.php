<?php

namespace block_shooter\listener;

use pocketmine\Server;
use game_chef\api\GameChef;
use pocketmine\event\Listener;
use block_shooter\GameTypeList;
use block_shooter\BossbarTypeList;
use game_chef\pmmp\bossbar\Bossbar;
use block_shooter\service\CommonGameService;
use game_chef\pmmp\events\PlayerQuitGameEvent;
use game_chef\pmmp\events\UpdatedGameTimerEvent;

class CommonGameListener implements Listener
{

    public function onQuitGame(PlayerQuitGameEvent $event)
    {
        $player = $event->getPlayer();
        $gameType = $event->getGameType();
        if (!in_array($gameType, GameTypeList::getAll())) return;

        CommonGameService::backToLobby($player);
    }

    public function onUpdatedGameTimer(UpdatedGameTimerEvent $event)
    {
        $gameId = $event->getGameId();
        $gameType = $event->getGameType();
        if (!in_array($gameType, GameTypeList::getAll())) return;

        //ボスバーの更新
        foreach (GameChef::getPlayerDataList($gameId) as $playerData) {
            $player = Server::getInstance()->getPlayer($playerData->getName());
            $bossbarType = BossbarTypeList::fromGameType($gameType);
            $bossbar = Bossbar::findByType($player, $bossbarType);
            
            //ボスバーの無い試合 or バグ
            //ほぼ１００％前者なので処理を終わらせる
            if ($bossbar === null) return;

            if ($event->getTimeLimit() === null) {
                $bossbar->updateTitle("経過時間:({$event->getElapsedTime()})");
            } else {
                $bossbar->updateTitle("{$event->getElapsedTime()}/{$event->getTimeLimit()}");
                $bossbar->updatePercentage(1 - ($event->getElapsedTime() / $event->getTimeLimit()));
            }
        }
    }
}
