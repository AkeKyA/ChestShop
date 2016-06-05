<?php
namespace ChestShop;

use onebone\economyapi\EconomyAPI;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat as TF;
use PocketMoney\PocketMoney;

class ChestShop extends PluginBase {
    public $moneyManager;
    public function onEnable() {
        if (!file_exists($this->getDataFolder())) @mkdir($this->getDataFolder());
        if($this->getServer()->getPluginManager()->getPlugin("PocketMoney") !== null) {
            $this->moneyManager = $this->getServer()->getPluginManager()->getPlugin("PocketMoney");
            $this->getLogger()->info("Money Manager set to PocketMoney");
        }elseif($this->getServer()->getPluginManager()->getPlugin("EconomyAPI") !== null) {
            $this->moneyManager = EconomyAPI::getInstance();
            $this->getLogger()->info("Money Manager set to EconomyAPI");
        }else{
            $this->getLogger()->error("No Economy Plugin Detected!");
            $this->getPluginLoader()->disablePlugin($this);
        }
        $this->getServer()->getPluginManager()->registerEvents(new EventListener($this, new DatabaseManager($this->getDataFolder() . 'ChestShop.sqlite3')), $this);
        $this->getLogger()->notice(TF::GREEN."Enabled!");
    }
    
    public function getMoney(Player $player) {
        if($this->moneyManager instanceof EconomyAPI) {
            return EconomyAPI::getInstance()->myMoney($player);
        }elseif($this->moneyManager instanceof PocketMoney) {
            return $this->moneyManager->getMoney($player->getName());
        }
        return null;
    }
    
    public function payMoney(Player $player, $Owner, $Cost) {
        if($this->moneyManager instanceof EconomyAPI) {
            EconomyAPI::getInstance()->reduceMoney($player, $Cost,true,"payment");
            EconomyAPI::getInstance()->addMoney($Owner,$Cost,true,"payment");
        }elseif($this->moneyManager instanceof PocketMoney) {
            return $this->moneyManager->payMoney($player->getName(), $Owner, $Cost);
        }
        return false;
    }
    
    public function onDisable() {
        $this->getLogger()->notice(TF::GREEN."Disabled!");
    }
    public function getMoneyManager() {
        return $this->moneyManager;
    }
    public function onCommand(CommandSender $sender, Command $command, $label, array $args) {
        if(strtolower($command) === "id") {
            if($args[0] === "help") {
                $sender->sendMessage(TF::YELLOW."/id <Item name>");
                return true;
            }
            $name = array_shift($args);
            $constants = array_keys((new \ReflectionClass("pocketmine\\item\\Item"))->getConstants());
            foreach ($constants as $constant) {
                if (stripos($constant, $name) !== false) {
                    $id = constant("pocketmine\\item\\Item::$constant");
                    $constant = str_replace("_", " ", $constant);
                    $sender->sendMessage("ID: $id $constant");
                }
            }
            return true;
        }
        return false;
    }
}