<?php


namespace block_shooter\scoreboard;


use block_shooter\block\Nexus;
use game_chef\models\TeamGame;
use game_chef\pmmp\scoreboard\Score;
use game_chef\pmmp\scoreboard\Scoreboard;
use game_chef\pmmp\scoreboard\ScoreboardSlot;
use game_chef\pmmp\scoreboard\ScoreSortType;
use pocketmine\Player;
use pocketmine\utils\TextFormat;

class CorePVPScoreboard extends Scoreboard
{
    static function init() {
        self::__setup(ScoreboardSlot::sideBar());
    }

    private static function create(TeamGame $game): Scoreboard {
        $scores = [];

        foreach ($game->getTeams() as $team) {
            $scores[] = new Score(
                $team->getTeamColorFormat() . $team->getName()
                . TextFormat::RESET . ":" . (Nexus::MAX_HEALTH - $team->getScore()->getValue())
            );
        }

        return parent::__create("===============", $scores, ScoreSortType::smallToLarge());
    }

    static function send(Player $player, TeamGame $game) {
        $scoreboard = self::create($game);
        parent::__send($player, $scoreboard);
    }

    static function update(Player $player, TeamGame $game) {
        $scoreboard = self::create($game);
        parent::__update($player, $scoreboard);
    }
}