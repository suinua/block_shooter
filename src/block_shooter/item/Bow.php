<?php

namespace block_shooter\item;

use block_shooter\service\BulletService;
use pocketmine\item\Item;
use pocketmine\item\ItemIds;
use pocketmine\Player;

class Bow extends \pocketmine\item\Bow
{
    public function setBulletItem(Item $item) : void {
        $this->getNamedTag()->setString("item", serialize($item->jsonSerialize()));
    }

    public function getBulletItem() : Item {
        if ($this->getNamedTag()->offsetExists("item")) {
            $serializedItem = $this->getNamedTag()->getString("item");

            return Item::jsonDeserialize(unserialize($serializedItem));
        }

        return Item::get(ItemIds::AIR);
    }

    public function onReleaseUsing(Player $player): bool
    {
        if ($this->getBulletItem()->getId() === ItemIds::AIR) {
            $player->sendTip("玉がセットされていません");
            $player->getInventory()->sendContents($player);
            return false;
        }

        if (!$player->getInventory()->contains($this->getBulletItem())) {
            $player->getInventory()->sendContents($player);
            return false;
        }

        $diff = $player->getItemUseDuration();
        $p = $diff / 20;
        $baseForce = min((($p ** 2) + $p * 2) / 3, 1);

        BulletService::spawnBullet($player, $this->getBulletItem(), $baseForce);

        $player->getInventory()->removeItem($this->getBulletItem());
        $player->getInventory()->sendContents($player);

        //使ってた玉がなくなったらAIRを入れる
        if (!$player->getInventory()->contains($this->getBulletItem())) {
            $this->setBulletItem(Item::get(ItemIds::AIR));
        }
        return true;
    }
}
