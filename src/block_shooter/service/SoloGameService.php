<?php

namespace block_shooter\usecase;

use block_shooter\BossbarTypeList;
use block_shooter\GameTypeList;
use block_shooter\scoreboard\SoloGameScoreboard;
use game_chef\api\FFAGameBuilder;
use game_chef\api\GameChef;
use game_chef\models\FFAGame;
use game_chef\models\Score;
use game_chef\pmmp\bossbar\Bossbar;
use pocketmine\entity\Attribute;
use pocketmine\entity\Effect;
use pocketmine\entity\EffectInstance;
use pocketmine\level\Position;
use pocketmine\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\TaskScheduler;
use pocketmine\Server;

class SoloGameService
{
    private static $scheduler;

    public static function setScheduler(TaskScheduler $scheduler): void
    {
        self::$scheduler = $scheduler;
    }

    public static function buildGame(string $mapName): void
    {
        $ffaGameBuilder = new FFAGameBuilder();
        $ffaGameBuilder->setGameType(GameTypeList::Solo());
        $ffaGameBuilder->setMaxPlayers(null);
        $ffaGameBuilder->setTimeLimit(600);
        $ffaGameBuilder->setVictoryScore(new Score(15));
        $ffaGameBuilder->setCanJumpIn(true);
        $ffaGameBuilder->selectMapByName($mapName);

        $ffaGame = $ffaGameBuilder->build();
        GameChef::registerGame($ffaGame);
    }

    public static function createGame(): void
    {
        $mapNames = GameChef::getAvailableFFAGameMapNames(GameTypeList::Solo());
        if (count($mapNames) === 0) {
            throw new \LogicException(GameTypeList::Solo() . "に対応したマップを作成してください");
        }

        $mapName = $mapNames[rand(0, count($mapNames) - 1)];
        self::buildGame($mapName);
    }

    public static function sendToGame(Player $player, FFAGame $game): void
    {
        $levelName = $game->getMap()->getLevelName();
        $level = Server::getInstance()->getLevelByName($levelName);

        $player->teleport($level->getSpawnLocation());
        $player->teleport(Position::fromObject($player->getSpawn(), $level));
        $player->getAttributeMap()->getAttribute(Attribute::MOVEMENT_SPEED)->setValue(0.25);
        $player->addEffect(new EffectInstance(Effect::getEffect(Effect::JUMP_BOOST), 600, 4));

        //ボスバー
        $bossbar = new Bossbar($player, BossbarTypeList::Solo(), "", 1.0);
        $bossbar->send();

        //スコアボード
        SoloGameScoreboard::send($player, $game);

        //インベントリ
        $player->getInventory()->setContents([
            //todo:インベントリのセット
        ]);
    }

    //参加できる試合を探し、参加するように
    public static function randomJoin(Player $player): void
    {
        $games = GameChef::getGamesByType(GameTypeList::Solo());
        if (count($games) === 0) {
            self::createGame();
        }

        $games = GameChef::getGamesByType(GameTypeList::Solo());
        $game = $games[0];
        $result = GameChef::joinFFAGame($player, $game->getId());
        if (!$result) {
            $player->sendMessage("試合に参加できませんでした");
        }

        //n人以上なら、10秒後に試合開始
        if (count(GameChef::getPlayerDataList($game->getId())) >= 2) {
            self::$scheduler->scheduleDelayedTask(new ClosureTask(function (int $tick) use ($game): void {
                GameChef::startGame($game->getId());
            }), 20 * 10);
        }
    }
}
