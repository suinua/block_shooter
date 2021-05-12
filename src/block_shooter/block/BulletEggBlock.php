<?php


namespace block_shooter\block;


use block_shooter\service\BulletService;
use pocketmine\block\Sponge;
use pocketmine\item\Item;
use pocketmine\Player;

class BulletEggBlock extends Sponge
{
    public function onBreak(Item $item, Player $player = null): bool {
        if ($player !== null) {
            $player->getLevel()->dropItem($this, Item::get(BulletService::BULLET_IDS[rand(0, count(BulletService::BULLET_IDS))-1]));
        }
        return parent::onBreak($item, $player);
    }
}