<?php


namespace block_shooter\listener;

use block_shooter\block\Nexus;
use block_shooter\GameTypeList;
use block_shooter\scoreboard\CorePVPScoreboard;
use block_shooter\service\CommonGameService;
use block_shooter\service\CorePVPGameService;
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
use pocketmine\event\player\PlayerRespawnEvent;
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

        $this->scheduler->scheduleDelayedTask(new ClosureTask(function (int $currentTick) use ($gameId): void {
            CorePVPGameService::setPrivateNameTag($gameId);
        }), 20);
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

        //2?????????????????????????????????????????????????????????
        if ($availableTeamCount >= 2) return;

        foreach (GameChef::getPlayerDataList($gameId) as $playerData) {
            $player = Server::getInstance()->getPlayer($playerData->getName());
            if ($player === null) continue;

            $player->sendMessage($winTeam->getTeamColorFormat() . $winTeam->getName() . TextFormat::RESET . "??????????????????");
            $player->sendMessage("10?????????????????????????????????");
        }

        $this->scheduler->scheduleDelayedTask(new ClosureTask(function (int $tick) use ($gameId) : void {
            //10????????????????????????????????????????????????foreach?????????????????????
            //????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????
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

        //???????????????
        foreach (GameChef::getPlayerDataList($gameId) as $gamePlayerData) {
            $gamePlayer = Server::getInstance()->getPlayer($gamePlayerData->getName());
            if ($gamePlayer === null) continue;
            $gamePlayer->sendMessage("{$player->getName()}???" . $team->getTeamColorFormat() . $team->getName() . TextFormat::RESET . "?????????????????????");
        }

        $player->sendMessage($team->getTeamColorFormat() . $team->getName() . TextFormat::RESET . "?????????????????????");

        //????????????
        $game = GameChef::findTeamGameById($gameId);
        if ($game->getStatus()->equals(GameStatus::Started())) {
            CorePVPGameService::sendToGame($player, $game);
            CorePVPGameService::setPrivateNameTag($gameId);
        }
    }

    public function onPlayerKilledPlayer(PlayerKilledPlayerEvent $event) {
        $gameId = $event->getGameId();
        $gameType = $event->getGameType();
        $attacker = $event->getAttacker();
        $killedPlayer = $event->getKilledPlayer();

        if (!$gameType->equals(GameTypeList::CorePVP())) return;
        if ($event->isFriendlyFire()) return;//???????????????????????????????????????

        $game = GameChef::findTeamGameById($gameId);

        $attackerData = GameChef::findPlayerData($attacker->getName());
        $attackerTeam = $game->findTeamById($attackerData->getBelongTeamId());

        $killedPlayerData = GameChef::findPlayerData($killedPlayer->getName());
        $killedPlayerTeam = $game->findTeamById($killedPlayerData->getBelongTeamId());

        //????????????????????????
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
        if ($team->getScore()->getValue() === Nexus::MAX_HEALTH) {
            GameChef::quitGame($player);
        } else {
            //??????????????????????????????
            GameChef::setTeamGamePlayerSpawnPoint($event->getPlayer());
        }
    }

    public function onPlayerReSpawn(PlayerRespawnEvent $event) {
        $player = $event->getPlayer();
        var_dump("respawn");
        if (!GameChef::isRelatedWith($player, GameTypeList::CorePVP())) return;

        CommonGameService::initPlayerStatus($player);
        $playerData = GameChef::findPlayerData($player->getName());
        CorePVPGameService::setPrivateNameTag($playerData->getBelongGameId());
    }

    public function onBreakNexus(BlockBreakEvent $event) {
        $block = $event->getBlock();
        if ($block->getId() !== Nexus::ID) return;

        $player = $event->getPlayer();
        $level = $player->getLevel();

        //??????????????????????????????????????????
        if (!MapService::isInstantWorld($level->getName())) return;

        //?????????????????????????????????????????????????????????
        $playerData = GameChef::findPlayerData($player->getName());
        if ($playerData->getBelongGameId() === null) {
            $event->setCancelled();
            return;
        }

        $game = GameChef::findGameById($playerData->getBelongGameId());

        //core pvp ?????????????????????
        if (!$game->getType()->equals(GameTypeList::CorePVP())) return;

        $targetTeam = null;
        foreach ($game->getTeams() as $team) {
            $teamNexusVector = $team->getCustomVectorData(Nexus::POSITION_DATA_KEY);
            if ($block->asVector3()->equals($teamNexusVector)) {
                $targetTeam = $team;
            }
        }

        if ($targetTeam === null) throw new \UnexpectedValueException("??????????????????????????????????????????????????????????????????");

        //?????????????????????????????????
        if ($targetTeam->getId()->equals($playerData->getBelongTeamId())) {
            $event->setCancelled();
            $player->sendTip("????????????????????????????????????????????????????????????");
            return;
        }

        //?????????????????????????????????(?????????????????????????????????????????????????????????)
        if ($targetTeam->getScore()->isBiggerThan(new Score(Nexus::MAX_HEALTH))) {
            $event->setCancelled();
            return;
        }

        CorePVPGameService::breakNexus($game, $targetTeam, $player, $block->asVector3());
    }
}