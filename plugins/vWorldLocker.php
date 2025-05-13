<?php

/*
 __PocketMine Plugin__
name=vWorldLocker
description=VanishMC plugin which allows to change world properties
version=1.1
apiversion=12.1
author=GameHerobrine
class=vWorldLocker
*/

class vWorldLocker implements Plugin{
	public $api;
	public $data, $dataPath;
	public $dataRaw;
	public static $DEFAULT_FLAGS = [
		"mobspawn" => true,
		"interact" => true,
		"damage" => true,
	];
	public function __construct(ServerAPI $api, $n = false){
		$this->api = $api;
		$api->event("server.start", [$this, "postInit"]);
	}
	
	public function init(){
		$this->dataPath = $this->api->plugin->configPath($this);
		$config = new Config("{$this->dataPath}/defaults.properties", CONFIG_PROPERTIES, self::$DEFAULT_FLAGS);
		$this->data = new Config("{$this->dataPath}/data.yml", CONFIG_YAML, []);
		$this->dataRaw = $this->data->getAll();
		$this->api->addHandler("player.block.break", [$this, "playerBreakHandler"], 0);
		$this->api->addHandler("player.block.place", [$this, "playerBreakHandler"], 0);
		$this->api->addHandler("entity.health.change", [$this, "healthChangeHandler"], 0);
		$this->api->console->register("worldlocker", "", [$this, "worldLockerCmdHandler"]);
		$this->api->console->alias("wl", "worldlocker");
	}

	public function postInit(){
		foreach($this->dataRaw as $levelName => $flags){
			$level = $this->api->level->get($levelName);
			if($level === false) {
				console("[WARNING] [WorldLocker] Failed to get level '$levelName'"); 
				continue;
			}
			$this->updateFlags($level, $flags);
		}
	}
	
	public function updateFlags(Level $level, $flags){
		if(!$this->getFlag($level->getName(), "mobspawn")){
			$level->mobSpawner = new class($level) extends MobSpawner {
				public function handle(){
					return false;
				}
			};
		}
	}
	public function initDefaultLevelFlags($levelName){
		console("[INFO] [vWorldLocker] Setting default flags to $levelName...");
		$this->dataRaw[$levelName] = self::$DEFAULT_FLAGS;
	}
	public function getDefaultFlag($flag){
		return self::$DEFAULT_FLAGS[$flag] ?? false;
	}
	
	
	public function setFlag($worldName, $flag, $s){
		if(!isset($this->dataRaw[$worldName]) || !is_array($this->dataRaw[$worldName])) $this->initDefaultLevelFlags($worldName);
		
		$this->dataRaw[$worldName][$flag] = $s;
		$this->data->setAll($this->dataRaw);
		$this->data->save();
		$this->updateFlags($this->api->level->get($worldName), $this->dataRaw[$worldName]);
	}
	
	public function getFlag($levelName, $flag){
		if(!isset($this->dataRaw[$levelName])){
			$this->initDefaultLevelFlags($levelName, $flag);
		}
		if(!isset($this->dataRaw[$levelName][$flag])){
			$this->dataRaw[$levelName][$flag] = $this->getDefaultFlag($flag);
		}
		
		return $this->dataRaw[$levelName][$flag];
	}
	
	public function healthChangeHandler($data, $event){
		$e = $data["entity"];
		$h = $data["health"];
		if($e instanceof Entity && $e->health > $h){
			if(!$this->getFlag($e->level->getName(), "damage")){
				return false;
			}
		}
	}
	
	public function playerBreakHandler($data, $event){
		$target = $data["target"];
		if(!$this->getFlag($target->level->getName(), "interact")){
			$player = $data["player"];
			if(!$this->api->ban->isOp($player)){
				$player->sendChat("You are not allowed to interact with this world.");
				return false;
			}
		}
	}
	
	public function worldLockerCmdHandler($cmd, $args, $issuer, $alias){
		switch($args[0] ?? ""){
			case "set":
				$flagName = $args[1] ?? "";
				
				switch($args[2] ?? ""){
					case "on":
						$status = true;
						break;
					case "off":
						$status = false;
						break;
					default:
						return "Invalid status.";
				}
				
				$worldName = $args[3] ?? "";
				if($this->api->level->get($worldName) === false){
					return "World '$worldName' does not exist or loaded.";
				}
				
				switch($flagName){
					case "mobspawn":	
					case "interact":
					case "damage":
						$this->setFlag($worldName, $flagName, $status);
						return "You have successfully updated world flags.";
					default:
						return "Invalid flag name. Use /$cmd flags to get all availible flags";
				}
				
			case "flags":
				return "Flags:\nmobspawn - Allows/Disallows mobs to spawn in the world.\ninteract - Allows/Disallows players to place/break blocks in the world\ndamage - Allow/Disallow damage in the world";
			
			default:
				return "/$cmd flags - list all flags you can set to the world\n/$cmd set {FLAG} <on/off> <worldName> - set the flag";
		}
	}
	
	public function __destruct(){}
}
