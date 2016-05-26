<?php
namespace ChestShop;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat as TF;

class ChestShop extends PluginBase {
    public $moneyManager;
    public function onEnable() {
        if (!file_exists($this->getDataFolder())) @mkdir($this->getDataFolder());
        if($this->getServer()->getServer()->getPluginManager()->getPlugin("PocketMoney") !== null) {
            $this->moneyManager = $this->getServer()->getPluginManager()->getPlugin("PocketMoney");
            $this->getLogger()->info("Money Manager set to PocketMoney");
        }elseif($this->getServer()->getPluginManager()->getPlugin("EcomomyAPI")) {
            $this->moneyManager = $this->getServer()->getPluginManager()->getPlugin("EconomyAPI");
            $this->getLogger()->info("Money Manager set to EconomyAPI");
        }else{
            //
        }
        $this->getServer()->getPluginManager()->registerEvents(new EventListener($this, new DatabaseManager($this->getDataFolder() . 'ChestShop.sqlite3')), $this);
        $this->getLogger->notice(TF::GREEEN."Enabled!");
    }
    public function onDisable() {
        $this->getLogger->notice(TF::GREEEN."Disabled!");
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
                    $sender->sendMessage("ID:$id $constant");
                }
            }
            return true;
        }
        return false;
    }
}
