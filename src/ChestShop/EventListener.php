<?php
namespace ChestShop;

use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\event\Listener;
use pocketmine\block\Block;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\Item;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\tile\Chest as TileChest;

class EventListener implements Listener {
    private $plugin;
    private $databaseManager;

    /**
     * EventListener constructor.
     * @param ChestShop $plugin
     * @param DatabaseManager $databaseManager
     */
    public function __construct(ChestShop $plugin, DatabaseManager $databaseManager) {
        $this->plugin = $plugin;
        $this->databaseManager = $databaseManager;
    }

    /**
     * @param PlayerInteractEvent $event
     */
    public function onPlayerInteract(PlayerInteractEvent $event) {
        $block = $event->getBlock();
        $player = $event->getPlayer();
        switch ($block->getId()) {
            case Block::SIGN_POST:
            case Block::WALL_SIGN:
                if (($shopInfo = $this->databaseManager->selectByCondition([
                        "signX" => $block->getX(),
                        "signY" => $block->getY(),
                        "signZ" => $block->getZ()
                    ])) === false) return;
                if ($shopInfo['shopOwner'] === $player->getName()) {
                    $player->sendMessage("Cannot purchase from your own shop!");
                    return;
                }
                $buyerMoney = $this->plugin->getMoney($player);
                if (!is_numeric($buyerMoney)) {
                    $player->sendMessage("Couldn't acquire your money data!");
                    return;
                }
                if ($buyerMoney < $shopInfo['price']) {
                    $player->sendMessage("Your money is not enough!");
                    return;
                }
                $chest = $player->getLevel()->getTile(new Vector3($shopInfo['chestX'], $shopInfo['chestY'], $shopInfo['chestZ']));
                if($chest instanceof TileChest);
                $itemNum = 0;
                $pID = $shopInfo['productID'];
                $pMeta = $shopInfo['productMeta'];
                for ($i = 0; $i < $chest->getSize(); $i++) {
                    $item = $chest->getInventory()->getItem($i);
                    if($item instanceof Item);
                    if ($item->getId() === $pID and $item->getDamage() === $pMeta) $itemNum += $item->getCount();
                }
                if ($itemNum < $shopInfo['saleNum']) {
                    $player->sendMessage("This shop is out of stock!");
                    if (($p = $this->plugin->getServer()->getPlayer($shopInfo['shopOwner'])) !== null) {
                        $productName = Item::fromString("$pID:$pMeta")->getName();
                            $p->sendMessage("Your ChestShop is out of stock! Replenish ID: $productName !");
                    }
                    return;
                }

                $player->getInventory()->addItem(clone Item::get((int)$shopInfo['productID'], (int)$shopInfo['productMeta'], (int)$shopInfo['saleNum']));

                $tmpNum = $shopInfo['saleNum'];
                for ($i = 0; $i < $chest->getSize(); $i++) {
                    $item = $chest->getInventory()->getItem($i);
                    if ($item->getId() === $pID and $item->getDamage() === $pMeta) {
                        if ($item->getCount() <= $tmpNum) {
                            $chest->getInventory()->removeItem(clone Item::get((int)$shopInfo['productID'], (int)$shopInfo['productMeta'], (int)$shopInfo['saleNum']));  //->setItem($i, Item::get(Item::AIR, 0, 0));
                            $tmpNum -= $item->getCount();
                        } else {
                            $chest->getInventory()->setItem($i, Item::get($item->getId(), $pMeta, $item->getCount() - $tmpNum));
                            break;
                        }
                    }
                }
                $this->plugin->payMoney($player, $shopInfo['shopOwner'], $shopInfo['price']);

                $player->sendMessage("Completed transaction");
                if (($p = $this->plugin->getServer()->getPlayer($shopInfo['shopOwner'])) !== null) {
                    if($pMeta !== null) {
                        $p->sendMessage("{$player->getName()} purchased ID: $pID:$pMeta '$'{$shopInfo['price']}");
                    }else{
                        $p->sendMessage("{$player->getName()} purchased ID: $pID '$'{$shopInfo['price']}");
                    }
                }
                break;

            case Block::CHEST:
                $shopInfo = $this->databaseManager->selectByCondition([
                    "chestX" => $block->getX(),
                    "chestY" => $block->getY(),
                    "chestZ" => $block->getZ()
                ]);
                if ($shopInfo !== false && $shopInfo['shopOwner'] !== $player->getName()) {
                    $player->sendMessage("This chest is protected!");
                    $event->setCancelled();
                }
                break;

            default:
                break;
        }
    }

    /**
     * @param BlockBreakEvent $event
     */
    public function onPlayerBreakBlock(BlockBreakEvent $event) {
        $block = $event->getBlock();
        $player = $event->getPlayer();

        switch ($block->getId()) {
            case Block::SIGN_POST:
            case Block::WALL_SIGN:
                $condition = [
                    "signX" => $block->getX(),
                    "signY" => $block->getY(),
                    "signZ" => $block->getZ()
                ];
                $shopInfo = $this->databaseManager->selectByCondition($condition);
                if ($shopInfo !== false) {
                    if ($shopInfo['shopOwner'] !== $player->getName()) {
                        $player->sendMessage("This sign has been protected!");
                        $event->setCancelled();
                    } else {
                        $this->databaseManager->deleteByCondition($condition);
                        $player->sendMessage("Closed your ChestShop");
                    }
                }
                break;

            case Block::CHEST:
                $condition = [
                    "chestX" => $block->getX(),
                    "chestY" => $block->getY(),
                    "chestZ" => $block->getZ()
                ];
                $shopInfo = $this->databaseManager->selectByCondition($condition);
                if ($shopInfo !== false) {
                    if ($shopInfo['shopOwner'] !== $player->getName()) {
                        $player->sendMessage("This chest has been protected!");
                        $event->setCancelled();
                    } else {
                        $this->databaseManager->deleteByCondition($condition);
                        $player->sendMessage("Closed your ChestShop");
                    }
                }
                break;

            default:
                break;
        }
    }

    /**
     * @param SignChangeEvent $event
     */
    public function onSignChange(SignChangeEvent $event) {
        $shopOwner = $event->getPlayer()->getName();
        $saleNum = $event->getLine(1);
        $price = $event->getLine(2);
        $productData = explode(":", $event->getLine(3));
        $pID = $this->isItem($id = array_shift($productData)) ? $id : false;
        $pMeta = ($meta = array_shift($productData)) ? $meta : 0;

        $sign = $event->getBlock();

        // Check shop format...
        if ($event->getLine(0) !== "") return;
        if (!is_numeric($saleNum) or $saleNum <= 0) return;
        if (!is_numeric($price) or $price < 0) return;
        if ($pID === false) return;
        if (($chest = $this->getSideChest($sign)) === false) return;
        //check shop count count to permission
        $chest = $this->databaseManager->selectByCondition(["shopOwner" => $shopOwner]);
        if (count($chest) >= 3 and !$event->getPlayer()->hasPermission("chestshop.make.unlimited")) return;
        if (!$event->getPlayer()->hasPermission("chestshop.make.3")) return;

        $productName = Item::fromString($event->getLine(3))->getName();
        $event->setLine(0, $shopOwner);
        $event->setLine(1, "Amount:$saleNum");
        $event->setLine(2, "Price:$price");
        $event->setLine(3, "$productName");

        $this->databaseManager->registerShop($shopOwner, $saleNum, $price, $pID, $pMeta, $sign, $chest);
    }

    /**
     * @param Position $pos
     * @return bool|Block
     */
    private function getSideChest(Position $pos) {
        $block = $pos->getLevel()->getBlock(new Vector3($pos->getX() + 1, $pos->getY(), $pos->getZ()));
        if ($block->getId() === Block::CHEST) return $block;
        $block = $pos->getLevel()->getBlock(new Vector3($pos->getX() - 1, $pos->getY(), $pos->getZ()));
        if ($block->getId() === Block::CHEST) return $block;
        $block = $pos->getLevel()->getBlock(new Vector3($pos->getX(), $pos->getY(), $pos->getZ() + 1));
        if ($block->getId() === Block::CHEST) return $block;
        $block = $pos->getLevel()->getBlock(new Vector3($pos->getX(), $pos->getY(), $pos->getZ() - 1));
        if ($block->getId() === Block::CHEST) return $block;
        $block = $pos->getLevel()->getBlock(new Vector3($pos->getX(), $pos->getY(), $pos->getZ() + 2));
        if ($block->getId() === Block::CHEST) return $block;
        $block = $pos->getLevel()->getBlock(new Vector3($pos->getX(), $pos->getY(), $pos->getZ() - 2));
        if ($block->getId() === Block::CHEST) return $block;
        $block = $pos->getLevel()->getBlock(new Vector3($pos->getX() + 2, $pos->getY(), $pos->getZ()));
        if ($block->getId() === Block::CHEST) return $block;
        $block = $pos->getLevel()->getBlock(new Vector3($pos->getX() - 2, $pos->getY(), $pos->getZ()));
        if ($block->getId() === Block::CHEST) return $block;
        return false;
    }

    private function isItem($id) {
        if (isset(Item::$list[$id])) return true;
        if (isset(Block::$list[$id])) return true;
        return false;
    }
}