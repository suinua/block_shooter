<?php

namespace block_shooter;

use block_shooter\block\Putty;
use block_shooter\item\Bow;
use block_shooter\listener\CommonGameListener;
use block_shooter\listener\CorePVPGameListener;
use block_shooter\listener\SoloGameListener;
use block_shooter\scoreboard\CorePVPScoreboard;
use block_shooter\scoreboard\SoloGameScoreboard;
use block_shooter\service\CorePVPGameService;
use block_shooter\service\SoloGameService;
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
    public function onEnable() {
        SoloGameScoreboard::init();
        CorePVPScoreboard::init();
        ItemFactory::registerItem(new Bow(), true);
        SoloGameService::setScheduler($this->getScheduler());
        CorePVPGameService::setScheduler($this->getScheduler());
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getServer()->getPluginManager()->registerEvents(new CommonGameListener(), $this);
        $this->getServer()->getPluginManager()->registerEvents(new SoloGameListener($this->getScheduler()), $this);
        $this->getServer()->getPluginManager()->registerEvents(new CorePVPGameListener($this->getScheduler()), $this);
    }

    public function onJoin(PlayerJoinEvent $event) {
        $pk = new GameRulesChangedPacket();
        $pk->gameRules["doImmediateRespawn"] = [1, true];
        $event->getPlayer()->sendDataPacket($pk);
    }

    public function onPacketReceived(DataPacketReceiveEvent $event) {
        $packet = $event->getPacket();
        if ($packet instanceof LoginPacket) {
            PlayerDeviceDataStore::save($packet);
        }
    }

    public function onChangeSlot(PlayerItemHeldEvent $event): void {
        $player = $event->getPlayer();
        if (!PlayerDeviceDataStore::isTap($player)) return;

        $currentItem = $player->getInventory()->getItemInHand();
        $nextItem = $event->getItem();

        if ($nextItem instanceof Bow) {
            if ($currentItem->getBlock()->getId() === BlockIds::AIR) return;
            if ($currentItem->getId() === Putty::ITEM_ID) return;
            if ($currentItem->getNamedTag()->offsetExists("cannot_shoot")) return;

            $nextItem->setBulletItem($currentItem);
            $player->getInventory()->setItem($event->getSlot(), $nextItem);
            $player->sendTip("???????????????????????????");
        }
    }

    public function onDropItem(InventoryTransactionEvent $event): void {
        $transaction = $event->getTransaction();
        $player = $transaction->getSource();
        foreach ($transaction->getActions() as $action) {
            if ($action instanceof DropItemAction) {
                $item = $action->getTargetItem();
                if (!$player->getInventory()->contains(new Bow())) return;

                $index = $player->getInventory()->first(new Bow());

                //todo:???????????????????????????????????????????????????????????????
                $bow = $player->getInventory()->getItem($index);
                if ($bow instanceof Bow) {
                    if ($item->getBlock()->getId() === BlockIds::AIR) return;
                    if ($item->getId() === Putty::ITEM_ID) return;
                    if ($item->getNamedTag()->offsetExists("cannot_shoot")) {
                        $event->setCancelled();
                        return;
                    }

                    $bow->setBulletItem($item);
                    $player->getInventory()->setItem($index, $bow);
                    $player->sendTip("???????????????????????????");
                    $event->setCancelled();
                }
            }
        }
    }

    public function tapAirWithBow() {
        //todo ??????????????????
    }
}
