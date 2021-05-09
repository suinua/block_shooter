<?php

namespace block_shooter\listener;

use block_shooter\scoreboard\SoloGameScoreboard;
use block_shooter\service\SoloGameService;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\Server;
use game_chef\api\GameChef;
use game_chef\models\Score;
use pocketmine\event\Listener;
use block_shooter\GameTypeList;
use block_shooter\service\CommonGameService;
use game_chef\models\GameStatus;
use pocketmine\utils\TextFormat;
use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\TaskScheduler;
use game_chef\pmmp\events\AddScoreEvent;
use game_chef\pmmp\events\StartedGameEvent;
use game_chef\pmmp\events\FinishedGameEvent;
use pocketmine\event\player\PlayerDeathEvent;
use game_chef\pmmp\events\PlayerJoinGameEvent;
use game_chef\pmmp\events\PlayerKilledPlayerEvent;

class SoloGameListener implements Listener
{
    private TaskScheduler $scheduler;

    public function __construct(TaskScheduler $scheduler) {
        $this->scheduler = $scheduler;
    }

    public function onJoin(PlayerJoinGameEvent $event) {
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
            SoloGameService::sendToGame($player, $game);
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
            SoloGameService::sendToGame($player, $game);

            //todo:試合の簡易ルールを伝える
            $player->sendMessage(TextFormat::GREEN . "試合が開始しました");
        }
    }

    public function onFinishedGame(FinishedGameEvent $event) {
        $gameId = $event->getGameId();
        $gameType = $event->getGameType();
        if (!$gameType->equals(GameTypeList::Solo())) return;

        foreach (GameChef::getPlayerDataList($gameId) as $playerData) {
            $player = Server::getInstance()->getPlayer($playerData->getName());
            if ($player === null) continue;

            $player->sendMessage("試合終了");
            //勝利判定 + メッセージ
        }

        $this->scheduler->scheduleDelayedTask(new ClosureTask(function (int $tick) use ($gameId) : void {
            //10秒間で退出する可能性があるから、foreachをもう一度書く
            //上で１プレイヤーずつタスクを書くこともできるが流れがわかりやすいのでこうしている
            foreach (GameChef::getPlayerDataList($gameId) as $playerData) {
                $player = Server::getInstance()->getPlayer($playerData->getName());
                if ($player === null) continue;

                CommonGameService::backToLobby($player);
            }

            GameChef::discardGame($gameId);
        }), 20 * 10);
    }

    public function onAddScore(AddScoreEvent $event) {
        $gameId = $event->getGameId();
        $gameType = $event->getGameType();
        if (!$gameType->equals(GameTypeList::Solo())) return;

        $game = GameChef::findFFAGameById($gameId);
        foreach (GameChef::getPlayerDataList($gameId) as $playerData) {
            $player = Server::getInstance()->getPlayer($playerData->getName());
            if ($player === null) continue;
            SoloGameScoreboard::update($player, $game);
        }
    }

    public function onPlayerDeath(PlayerDeathEvent $event) {
        $player = $event->getPlayer();
        if (!GameChef::isRelatedWith($player, GameTypeList::Solo())) return;

        //スポーン地点を再設定
        GameChef::setFFAPlayerSpawnPoint($event->getPlayer());

        $event->setDrops([]);
        $event->setXpDropAmount(0);
    }

    public function onPlayerReSpawn(PlayerRespawnEvent $event) {
        $player = $event->getPlayer();
        if (!GameChef::isRelatedWith($player, GameTypeList::Solo())) return;

        $playerData = GameChef::findPlayerData($player->getName());
        $game = GameChef::findGameById($playerData->getBelongGameId());
        CommonGameService::initPlayerStatus($player);
    }

    public function onPlayerKilledPlayer(PlayerKilledPlayerEvent $event) {
        $gameId = $event->getGameId();
        $gameType = $event->getGameType();
        $attacker = $event->getAttacker();
        $killedPlayer = $event->getKilledPlayer();
        if (!$gameType->equals(GameTypeList::Solo())) return;

        //メッセージを送信
        $message = "[{$attacker->getName()}] killed [{$killedPlayer->getName()}]";
        foreach (GameChef::getPlayerDataList($gameId) as $playerData) {
            $gamePlayer = Server::getInstance()->getPlayer($playerData->getName());
            $gamePlayer->sendMessage($message);
        }

        //スコアの追加
        GameChef::addFFAGameScore($gameId, $attacker->getName(), new Score(1));
    }
}
