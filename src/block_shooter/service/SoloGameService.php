<?php

namespace block_shooter\usecase;

use block_shooter\BossbarTypeList;
use block_shooter\GameTypeList;
use block_shooter\item\Bow;
use block_shooter\scoreboard\SoloGameScoreboard;
use game_chef\api\FFAGameBuilder;
use game_chef\api\GameChef;
use game_chef\models\FFAGame;
use game_chef\models\FFAGameMap;
use game_chef\models\Score;
use game_chef\pmmp\bossbar\Bossbar;
use pocketmine\entity\Attribute;
use pocketmine\entity\Effect;
use pocketmine\entity\EffectInstance;
use pocketmine\item\Arrow;
use pocketmine\item\Item;
use pocketmine\item\ItemIds;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\TaskScheduler;
use pocketmine\Server;

class SoloGameService
{
    private static $scheduler;

    public static function setScheduler(TaskScheduler $scheduler): void {
        self::$scheduler = $scheduler;
    }

    public static function buildGame(string $mapName): void {
        $ffaGameBuilder = new FFAGameBuilder();
        try {
            $ffaGameBuilder->setGameType(GameTypeList::Solo());
            $ffaGameBuilder->setMaxPlayers(null);
            $ffaGameBuilder->setTimeLimit(600);
            $ffaGameBuilder->setVictoryScore(new Score(15));
            $ffaGameBuilder->setCanJumpIn(true);
            $ffaGameBuilder->selectMapByName($mapName);

            $ffaGame = $ffaGameBuilder->build();
            GameChef::registerGame($ffaGame);
        } catch (\Exception $e) {
            Server::getInstance()->getLogger()->error($e->getMessage());
        }

    }

    public static function createGame(): void {
        $mapNames = GameChef::getFFAGameMapNamesByType(GameTypeList::Solo());
        if (count($mapNames) === 0) {
            throw new \LogicException(GameTypeList::Solo() . "に対応したマップを作成してください");
        }

        $mapName = $mapNames[rand(0, count($mapNames) - 1)];
        self::buildGame($mapName);
    }

    public static function sendToGame(Player $player, FFAGame $game): void {
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
            new Bow(),
            Item::get(ItemIds::STONE, 0, 5),
        ]);

        $player->getInventory()->setItem(9, new Arrow());
    }

    //参加できる試合を探し、参加するように
    public static function randomJoin(Player $player): void {
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

    public static function spawnBulletItems(FFAGameMap $map): void {
        $spawnPoints = $map->getCustomArrayVectorData("bullets");
        $count = intval(count($spawnPoints) / 1.5);
        $indexList = array_rand($spawnPoints, $count);
        $level = Server::getInstance()->getLevelByName($map->getLevelName());
        foreach ($indexList as $index) {
            self::spawnBulletItem($level, $spawnPoints[$index]);
        }
    }

    public static function spawnBulletItem(Level $level, Vector3 $vector3): void {
        //todo:玉の種類を増やす
        $level->dropItem($vector3, Item::get(ItemIds::STONE));
    }
}
