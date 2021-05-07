<?php

namespace block_shooter\service;

use block_shooter\entity\BulletEntity;
use pocketmine\block\Stone;
use pocketmine\entity\Entity;
use pocketmine\item\Item;
use pocketmine\item\ItemIds;
use pocketmine\Player;

class BulletService
{


    public static function spawnBullet(Player $player, Item $bulletItem, float $force): void
    {
        $nbt = Entity::createBaseNBT($player->asVector3()->add(0, $player->getEyeHeight()), $player->getDirectionVector()->multiply(2), $player->getYaw(), $player->getPitch());

        $itemTag = $bulletItem->nbtSerialize();
        $itemTag->setName("Item");
        $nbt->setTag($itemTag);

        $entity = new BulletEntity($player->getLevel(), $nbt);
        $entity->setOwner($player->getName());
        $entity->setMotion($entity->getMotion()->multiply($force));

        $entity->spawnToAll();
    }

    public static function calculateDamage(Player $shooter, Entity $target, Item $bullet): void
    {
        if (!($target instanceof Player)) {
            return;
        }
    }
}
