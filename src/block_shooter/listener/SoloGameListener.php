<?php

namespace block_shooter\listener;

use pocketmine\Server;
use game_chef\api\GameChef;
use pocketmine\event\Listener;
use block_shooter\GameTypeList;
use game_chef\models\GameStatus;
use pocketmine\utils\TextFormat;
use block_shooter\usecase\SoloGameUsecase;
use game_chef\pmmp\events\StartedGameEvent;
use game_chef\pmmp\events\PlayerJoinGameEvent;
use pocketmine\scheduler\TaskScheduler;

class SoloGameListener implements Listener
{
    private TaskScheduler $scheduler;

    public function __construct(TaskScheduler $scheduler) {
        $this->scheduler = $scheduler;
    }

    public function onJoin(PlayerJoinGameEvent $event)
    {
        $player = $event->getPlayer();
        $gameId = $event->getGameId();
        $gameType = $event->getGameType();
        if (!$gameType->equals(GameTypeList::Solo())) {
            return;
        }

        //メッセージ
        foreach (GameChef::getPlayerDataList($gameId) as $gamePlayerData) {
            $gamePlayer = Server::getInstance()->getPlayer($gamePlayerData->getName());
            if ($gamePlayer === null) {
                continue;
            }

            $gamePlayer->sendMessage(TextFormat::GREEN . "{$player->getName()}が参加しました");
        }

        $player->sendMessage(TextFormat::GREEN . "試合に参加しました");

        //途中参加
        $game = GameChef::findFFAGameById($gameId);
        if ($game->getStatus()->equals(GameStatus::Started())) {
            SoloGameUsecase::sendToGame($player, $game);
        }
    }


    public function onStartGame(StartedGameEvent $event) {
        $gameId = $event->getGameId();
        $gameType = $event->getGameType();
        if (!$gameType->equals(GameTypeList::Solo())) return;

        $game = GameChef::findFFAGameById($gameId);
        GameChef::setFFAPlayersSpawnPoint($gameId);

        foreach (GameChef::getPlayerDataList($gameId) as $playerData) {
            $player = Server::getInstance()->getPlayer($playerData->getName());
            SoloGameUsecase::sendToGame($player, $game);

            //todo:試合の簡易ルールを伝える
            $player->sendMessage(TextFormat::GREEN .  "試合が開始しました");
        }
    }

}
