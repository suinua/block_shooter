<?php

namespace block_shooter\service;

use block_shooter\entity\BulletEntity;
use pocketmine\block\Ice;
use pocketmine\entity\Effect;
use pocketmine\entity\EffectInstance;
use pocketmine\entity\Entity;
use pocketmine\item\Item;
use pocketmine\item\ItemIds;
use pocketmine\level\Level;
use pocketmine\level\particle\HugeExplodeParticle;
use pocketmine\level\particle\SnowballPoofParticle;
use pocketmine\level\sound\GenericSound;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\Player;

class BulletService
{
    public static function spawnBullet(Player $player, Item $bulletItem, float $force): void {
        $nbt = Entity::createBaseNBT($player->asVector3()->add(0, $player->getEyeHeight()), $player->getDirectionVector()->multiply(2), $player->getYaw(), $player->getPitch());

        $itemTag = $bulletItem->nbtSerialize();
        $itemTag->setName("Item");
        $nbt->setTag($itemTag);

        $entity = new BulletEntity($player->getLevel(), $nbt);
        $entity->setOwner($player->getName());
        $entity->setMotion($entity->getMotion()->multiply($force));

        $entity->spawnToAll();
    }

    public static function hit(BulletEntity $bulletEntity): void {
        $itemId = $bulletEntity->getItem()->getId();
        $level = $bulletEntity->getLevel();
        switch ($itemId) {
            case ItemIds::TNT:
                self::hitTNT($level, $bulletEntity->asVector3());
                return;
            case ItemIds::SNOW_BLOCK:
                self::hitSnow($level, $bulletEntity->asVector3());
                return;
            case ItemIds::SAND:
                self::hitSand($level, $bulletEntity->asVector3());
                return;
            case ItemIds::REDSTONE_BLOCK:
                self::hitRedStone($level, $bulletEntity->asVector3());
                return;
            case ItemIds::ICE:
                self::hitIce($level, $bulletEntity->asVector3());
                return;
        }
    }

    private static function hitTNT(Level $level, Vector3 $pos) {
        $level->addParticle(new HugeExplodeParticle($pos));
        $level->addSound(new GenericSound($pos, LevelSoundEventPacket::SOUND_EXPLODE));
        foreach (self::getAroundPlayer($level, $pos, 2.5) as $player) {
            $player->kill();
        }
    }

    private static function hitSnow(Level $level, Vector3 $pos) {
        $level->addParticle(new SnowballPoofParticle($pos));
        $level->addSound(new GenericSound($pos, LevelSoundEventPacket::SOUND_BUCKET_FILL_POWDER_SNOW));
        foreach (self::getAroundPlayer($level, $pos, 2.5) as $player) {
            $player->addEffect(new EffectInstance(Effect::getEffect(Effect::SLOWNESS), 20 * 5, 1));
        }
    }

    private static function hitSand(Level $level, Vector3 $pos) {
        $level->addSound(new GenericSound($pos, LevelSoundEventPacket::SOUND_AMBIENT_SOULSAND_VALLEY_ADDITIONS));
        foreach (self::getAroundPlayer($level, $pos, 4) as $player) {
            $player->addEffect(new EffectInstance(Effect::getEffect(Effect::BLINDNESS), 20 * 5, 1));
        }
    }

    private static function hitRedStone(Level $level, Vector3 $pos) {
        $level->addSound(new GenericSound($pos, LevelSoundEventPacket::SOUND_AMBIENT_SOULSAND_VALLEY_ADDITIONS));
        foreach (self::getAroundPlayer($level, $pos, 10) as $player) {
            //todo private name tag
        }
    }

    private static function hitIce(Level $level, Vector3 $pos) {
        $level->addSound(new GenericSound($pos, LevelSoundEventPacket::SOUND_AMBIENT_SOULSAND_VALLEY_ADDITIONS));
        foreach (self::getAroundPlayer($level, $pos, 2.5) as $player) {
            $player->setImmobile();
            $level->setBlock($player, new Ice());
            //todo ３秒後に戻す
        }
    }

    /**
     * @param Level $level
     * @param Vector3 $center
     * @param float $distance
     * @return Player[]
     */
    private static function getAroundPlayer(Level $level, Vector3 $center, float $distance): array {
        $result = [];
        foreach ($level->getPlayers() as $player) {
            if ($player->distance($center) <= $distance) {
                $result[] = $player;
            }
        }
        return $result;
    }
}
