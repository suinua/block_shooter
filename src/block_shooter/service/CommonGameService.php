<?php

namespace block_shooter\service;

use block_shooter\block\Putty;
use block_shooter\form\JoinGameForm;
use block_shooter\item\Bow;
use block_shooter\scoreboard\SoloGameScoreboard;
use game_chef\pmmp\bossbar\Bossbar;
use game_chef\pmmp\hotbar_menu\HotbarMenu;
use game_chef\pmmp\hotbar_menu\HotbarMenuItem;
use pocketmine\entity\Attribute;
use pocketmine\entity\Effect;
use pocketmine\entity\EffectInstance;
use pocketmine\item\Arrow;
use pocketmine\item\Item;
use pocketmine\item\ItemIds;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

class CommonGameService
{
    public static function backToLobby(Player $player): void {
        $level = Server::getInstance()->getDefaultLevel();
        $player->teleport($level->getSpawnLocation());
        $player->getAttributeMap()->getAttribute(Attribute::MOVEMENT_SPEED)->setValue(0.1);
        $player->removeAllEffects();

        $menu = new HotbarMenu($player, [
            new HotbarMenuItem(
                ItemIds::EMERALD,
                0,
                TextFormat::GREEN . "試合に参加",
                function (Player $player) {
                    $player->sendForm(new JoinGameForm());
                }
            )
        ]);
        $menu->send();

        //ボスバー削除
        foreach (Bossbar::getBossbars($player) as $bossbar) {
            $bossbar->remove();
        }

        //スコアボード削除
        SoloGameScoreboard::delete($player);
    }

    public static function initPlayerStatus(Player $player): void {
        //エフェクト
        $player->getAttributeMap()->getAttribute(Attribute::MOVEMENT_SPEED)->setValue(0.4);
        $player->addEffect(new EffectInstance(Effect::getEffect(Effect::JUMP_BOOST), 20 * 600, 5));


        //インベントリ
        $player->getInventory()->setContents([
            new Bow(),
            Item::get(ItemIds::STONE, 0, 5),
            Item::get(Putty::ITEM_ID, 0, 20)
        ]);
        $player->getInventory()->setItem(9, new Arrow());

        //エリトラ
        $player->getArmorInventory()->setChestplate(Item::get(ItemIds::ELYTRA));
    }
}
