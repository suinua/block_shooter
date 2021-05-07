<?php

namespace block_shooter;

use block_shooter\item\Bow;
use block_shooter\scoreboard\SoloGameScoreboard;
use pocketmine\block\BlockIds;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\inventory\transaction\action\DropItemAction;
use pocketmine\item\ItemFactory;
use pocketmine\network\mcpe\protocol\GameRulesChangedPacket;
use pocketmine\network\mcpe\protocol\LoginPacket;
use pocketmine\plugin\PluginBase;

class Main extends PluginBase implements Listener
{
    public function onEnable()
    {
        SoloGameScoreboard::init();
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        ItemFactory::registerItem(new Bow(), true);
    }

    public function onJoin(PlayerJoinEvent $event)
    {
        $pk = new GameRulesChangedPacket();
        $pk->gameRules["doImmediateRespawn"] = [1, true];
        $event->getPlayer()->sendDataPacket($pk);
    }

    public function onPacketReceived(DataPacketReceiveEvent $event)
    {
        $packet = $event->getPacket();
        if ($packet instanceof LoginPacket) {
            PlayerDeviceDataStore::save($packet);
        }
    }

    public function onChangeSlot(PlayerItemHeldEvent $event): void
    {
        $player = $event->getPlayer();
        if (!PlayerDeviceDataStore::isTap($player)) return;

        $currentItem = $player->getInventory()->getItemInHand();
        $nextItem = $event->getItem();

        if ($nextItem instanceof Bow) {
            if ($currentItem->getBlock()->getId() === BlockIds::AIR) return;
            if ($currentItem->getNamedTag()->offsetExists("cannot_shoot")) return;

            $nextItem->setBulletItem($currentItem);
            $player->getInventory()->setItem($event->getSlot(), $nextItem);
            $player->sendTip("玉をセットしました");
        }
    }

    public function onDropItem(InventoryTransactionEvent $event): void
    {
        $transaction = $event->getTransaction();
        $player = $transaction->getSource();
        foreach ($transaction->getActions() as $action) {
            if ($action instanceof DropItemAction) {
                $item = $action->getTargetItem();
                if (!$player->getInventory()->contains(new Bow())) return;

                $index = $player->getInventory()->first(new Bow());

                //todo:アイテム全部捨てられなくしていいんじゃね？
                $bow = $player->getInventory()->getItem($index);
                if ($bow instanceof Bow) {
                    if ($item->getNamedTag()->offsetExists("cannot_shoot")) {
                        $event->setCancelled();
                        return;
                    }

                    $bow->setBulletItem($item);
                    $player->getInventory()->setItem($index, $bow);
                    $player->sendTip("玉をセットしました");
                    $event->setCancelled();
                }
            }
        }
    }

    public function tapAirWithBow() {
        //todo 前に高速移動
    }
}
