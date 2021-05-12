<?php


namespace block_shooter\block;


use block_shooter\item\BulletIds;
use block_shooter\service\BulletService;
use pocketmine\block\Sponge;
use pocketmine\item\Item;
use pocketmine\Player;

class BulletEggBlock extends Sponge
{
    public function onBreak(Item $item, Player $player = null): bool {
        if ($player !== null) {
            $player->getLevel()->dropItem($this, Item::get(BulletIds::IDS[rand(0, count(BulletIds::IDS)) - 1]));
        }
        return parent::onBreak($item, $player);
    }
}