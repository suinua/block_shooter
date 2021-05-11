<?php


namespace block_shooter\listener;

use block_shooter\block\Nexus;
use block_shooter\GameTypeList;
use block_shooter\scoreboard\CorePVPScoreboard;
use block_shooter\service\CommonGameService;
use block_shooter\service\SoloGameService;
use core_pvp\service\CorePVPGameService;
use game_chef\api\GameChef;
use game_chef\models\GameStatus;
use game_chef\models\Score;
use game_chef\pmmp\events\AddScoreEvent;
use game_chef\pmmp\events\FinishedGameEvent;
use game_chef\pmmp\events\PlayerJoinGameEvent;
use game_chef\pmmp\events\PlayerKilledPlayerEvent;
use game_chef\pmmp\events\StartedGameEvent;
use game_chef\services\MapService;
use pocketmine\entity\Entity;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\TaskScheduler;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

class CorePVPGameListener implements Listener
{
    private TaskScheduler $scheduler;

    public function __construct(TaskScheduler $scheduler) {
        $this->scheduler = $scheduler;
    }

    public function onStartedGame(StartedGameEvent $event) {
        $gameId = $event->getGameId();
        $gameType = $event->getGameType();
        if (!$gameType->equals(GameTypeList::CorePVP())) return;

        $game = GameChef::findTeamGameById($gameId);
        GameChef::setTeamGamePlayersSpawnPoint($gameId);

        foreach (GameChef::getPlayerDataList($gameId) as $playerData) {
            $player = Server::getInstance()->getPlayer($playerData->getName());
            CorePVPGameService::sendToGame($player, $game);
        }
    }

    public function onFinishedGame(FinishedGameEvent $event) {
        $gameId = $event->getGameId();
        $gameType = $event->getGameType();
        if (!$gameType->equals(GameTypeList::CorePVP())) return;

        $winTeam = null;
        $availableTeamCount = 0;
        $game = GameChef::findGameById($gameId);
        foreach ($game->getTeams() as $team) {
            if ($team->getScore()->getValue() < Nexus::MAX_HEALTH) {
                $availableTeamCount++;
                $winTeam = $team;
            }
        }

        //2チーム以上残っていたら試合は終了しない
        if ($availableTeamCount >= 2) return;

        foreach (GameChef::getPlayerDataList($gameId) as $playerData) {
            $player = Server::getInstance()->getPlayer($playerData->getName());
            if ($player === null) continue;

            if ($playerData->getBelongTeamId()->equals($winTeam->getId())) {
                Entity::createEntity(
                    "Firework",
                    $player->getLevel(),
                    Entity::createBaseNBT($player->getPosition(), null, 0, 0),
                )->spawnToAll();

            }

            $player->sendMessage($winTeam->getTeamColorFormat() . $winTeam->getName() . TextFormat::RESET . "の勝利！！！");
            $player->sendMessage("10秒後にロビーに戻ります");
            CommonGameService::backToLobby($player);
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

    public function onPlayerJoinedGame(PlayerJoinGameEvent $event) {
        $player = $event->getPlayer();
        $gameId = $event->getGameId();
        $gameType = $event->getGameType();
        $teamId = $event->getTeamId();
        if (!$gameType->equals(GameTypeList::CorePVP())) return;

        $game = GameChef::findGameById($gameId);
        $team = $game->getTeamById($teamId);

        //メッセージ
        foreach (GameChef::getPlayerDataList($gameId) as $gamePlayerData) {
            $gamePlayer = Server::getInstance()->getPlayer($gamePlayerData->getName());
            if ($gamePlayer === null) continue;
            $gamePlayer->sendMessage("{$player->getName()}が" . $team->getTeamColorFormat() . $team->getName() . TextFormat::RESET . "に参加しました");
        }

        $player->sendMessage($team->getTeamColorFormat() . $team->getName() . TextFormat::RESET . "に参加しました");

        //途中参加
        $game = GameChef::findFFAGameById($gameId);
        if ($game->getStatus()->equals(GameStatus::Started())) {
            SoloGameService::sendToGame($player, $game);
        }
    }

    public function onPlayerKilledPlayer(PlayerKilledPlayerEvent $event) {
        $gameId = $event->getGameId();
        $gameType = $event->getGameType();
        $attacker = $event->getAttacker();
        $killedPlayer = $event->getKilledPlayer();

        if (!$gameType->equals(GameTypeList::CorePVP())) return;
        if ($event->isFriendlyFire()) return;//試合の設定上ありえないけど

        $game = GameChef::findTeamGameById($gameId);

        $attackerData = GameChef::findPlayerData($attacker->getName());
        $attackerTeam = $game->findTeamById($attackerData->getBelongTeamId());

        $killedPlayerData = GameChef::findPlayerData($killedPlayer->getName());
        $killedPlayerTeam = $game->findTeamById($killedPlayerData->getBelongTeamId());

        //メッセージを送信
        $message = $attackerTeam->getTeamColorFormat() . "[{$attacker->getName()}]" . TextFormat::RESET .
            " killed" .
            $killedPlayerTeam->getTeamColorFormat() . " [{$killedPlayer->getName()}]";
        foreach (GameChef::getPlayerDataList($gameId) as $playerData) {
            $gamePlayer = Server::getInstance()->getPlayer($playerData->getName());
            $gamePlayer->sendMessage($message);
        }
    }

    public function onAddedScore(AddScoreEvent $event) {
        $gameId = $event->getGameId();
        $gameType = $event->getGameType();
        if (!$gameType->equals(GameTypeList::CorePVP())) return;

        $game = GameChef::findTeamGameById($gameId);
        foreach (GameChef::getPlayerDataList($gameId) as $playerData) {
            $player = Server::getInstance()->getPlayer($playerData->getName());
            CorePVPScoreboard::update($player, $game);
        }
    }

    public function onPlayerDeath(PlayerDeathEvent $event) {
        $player = $event->getPlayer();
        if (!GameChef::isRelatedWith($player, GameTypeList::CorePVP())) return;

        $playerData = GameChef::findPlayerData($player->getName());
        $game = GameChef::findGameById($playerData->getBelongGameId());
        $team = $game->getTeamById($playerData->getBelongTeamId());
        if ($team->getScore()->getValue() !== Nexus::MAX_HEALTH) {
            GameChef::quitGame($player);
        } else {
            //スポーン地点を再設定
            GameChef::setTeamGamePlayerSpawnPoint($event->getPlayer());
        }

    }

    public function onBreakNexus(BlockBreakEvent $event) {
        $block = $event->getBlock();
        if ($block->getId() !== Nexus::ID) return;

        $player = $event->getPlayer();
        $level = $player->getLevel();

        //試合中のマップじゃなかったら
        if (!MapService::isInstantWorld($level->getName())) return;

        //プレイヤーが試合に参加していなかったら
        $playerData = GameChef::findPlayerData($player->getName());
        if ($playerData->getBelongGameId() === null) {
            $event->setCancelled();
            return;
        }

        $game = GameChef::findGameById($playerData->getBelongGameId());

        //core pvp じゃなかったら
        if (!$game->getType()->equals(GameTypeList::CorePVP())) return;

        $targetTeam = null;
        foreach ($game->getTeams() as $team) {
            $teamNexusVector = $team->getCustomVectorData(Nexus::POSITION_DATA_KEY);
            if ($block->asVector3()->equals($teamNexusVector)) {
                $targetTeam = $team;
            }
        }

        if ($targetTeam === null) throw new \UnexpectedValueException("そのネクサスを持つチームが存在しませんでした");

        //自軍のネクサスだったら
        if ($targetTeam->getId()->equals($playerData->getBelongTeamId())) {
            $event->setCancelled();
            $player->sendTip("自軍のネクサスを破壊することはできません");
            return;
        }

        //すでに死んだチームなら(ネクサスを置き換えるからありえないけど)
        if ($targetTeam->getScore()->isBiggerThan(new Score(Nexus::MAX_HEALTH))) {
            $event->setCancelled();
            return;
        }

        CorePVPGameService::breakNexus($game, $targetTeam, $player, $block->asVector3());
    }
}