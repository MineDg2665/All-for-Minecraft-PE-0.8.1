<?php
/*
__PocketMine Plugin__
name=tPlayerInteract
version=1.1
description=Plugin to turn off and on build, touch and destroy mode 
author=Tema1d/GameHerobrine
class=tPlayerInt
apiversion=12,12.1
*/


class tPlayerInt implements Plugin{
	private $api, $sessions, $path, $config, $userstate;
	
	const INTERNAL_VERSION = 0;
	private static $NO_PERMISSIONS = "You are not allowed to interact with the server.";
	private static $GRANTED = "You have received permissions to interact with the server.";
	private static $REVOKED = "You are no longer able to interact with the server.";
	private static $DEFAULT_USERSTATE = false;
	
	private static $ALLOW_BLOCK_DESTRUCTION = false;
	private static $ALLOW_BLOCK_PLACEMENT = false;

	public function __construct(ServerAPI $api, $server = false){
		$this->api = $api;
		$this->sessions = array();
	}

	public function init(){
		$defaults["plugin"] = [
			"internal_version" => self::INTERNAL_VERSION,
			"defaults" => [ 
				"userstate" => self::$DEFAULT_USERSTATE,
				"allow-chat" => false,
				"allow-me-and-tell-commands" => false,
				"allow-block-destruction" => self::$ALLOW_BLOCK_DESTRUCTION,
				"allow-block-placement" => self::$ALLOW_BLOCK_PLACEMENT,
				"allow-pvp" => false
			], 
			"messages" => [ 
				"enabled" => self::$GRANTED, 
				"disabled" => self::$REVOKED, 
				"cannotbuild" => self::$NO_PERMISSIONS,
			] 
		];
		
		$path = $this->api->plugin->configPath($this);
		
		if(is_file("$path/config.json")){
			console("[NOTICE] [tPlayerInteract] Found old config. Converting...");
			$cfg = new Config("$path/config.json", CONFIG_JSON);
			$def = $cfg->get("defaults")["userstate"];
			self::$DEFAULT_USERSTATE = $def == "false" ? false : true;
			self::$GRANTED = $cfg->get("messages")["enabled"];
			self::$REVOKED = $cfg->get("messages")["disabled"];
			self::$NO_PERMISSIONS = $cfg->get("messages")["cannotbuild"];
			console("[NOTICE] [tPlayerInteract] Renaming old config to $path/configold.json...");
			rename("$path/config.json", "$path/configold.json");
		}
		
		$this->config['plugin'] = new Config("$path/config.yml", CONFIG_YAML, $defaults['plugin']);
		$this->config['userstate'] = new Config("$path/userstate.yml", CONFIG_YAML, []); 
		
		if($this->config['plugin']->get("internal_version") != self::INTERNAL_VERSION){
			console("[WARNING] [tPlayerInteract] Config version mismatch found.");
		}
		
		$cfg_msgs = $this->config['plugin']->get("messages");
		$cfg_defs = $this->config['plugin']->get("defaults");
		self::$NO_PERMISSIONS = $cfg_msgs["cannotbuild"];
		self::$GRANTED = $cfg_msgs["enabled"];
		self::$REVOKED = $cfg_msgs["disabled"];
		
		$DEFAULT_USERSTATE = $cfg_defs["userstate"];
		$ALLOW_CHAT = $cfg_defs["allow-chat"];
		$ALLOW_COMMANDS = $cfg_defs["allow-me-and-tell-commands"];
		self::$ALLOW_BLOCK_DESTRUCTION = $cfg_defs["allow-block-destruction"];
		self::$ALLOW_BLOCK_PLACEMENT = $cfg_defs["allow-block-placement"];
		$ALLOW_PVP = $cfg_defs["allow-pvp"];
		
		if(is_file("$path/userstate.json")){
			console("[NOTICE] [tPlayerInt] Found old userstate. Converting...");
			$cfg = new Config("$path/userstate.json", CONFIG_JSON);
			foreach($cfg->getAll() as $user => $status){
				$this->config['userstate']->set($user, $status == "false" ? false : true);
			}
			$this->config['userstate']->save();
			console("[NOTICE] [tPlayerInteract] Renaming old userstate to $path/userstateold.json...");
			rename("$path/userstate.json", "$path/userstateold.json");
		}
		$this->api->addHandler("player.block.touch", 				[$this, "blockTouch"], 660);

		if(!$ALLOW_CHAT) $this->api->addHandler("player.chat",			[$this, "playerChat"], 660);
		if(!$ALLOW_PVP) $this->api->addHandler("player.interact",		[$this, "punchMob"],   660);
		if(!$ALLOW_COMMANDS) $this->api->addHandler("console.command",		[$this, "cmdEvent"],   660);

		$this->api->console->register("addint", "Change interaction mode.", array($this, "addint"));
	}
	
	public function setPermissions($username, $status){
		$this->config['userstate']->set($username, $status);
		$this->config['userstate']->save();
	}
	
	public function hasPermissions($username){
		$username = strtolower($username);
		
		if($this->config["userstate"]->exists($username)){
			return $this->config["userstate"]->get($username);
		}else{
			$this->setPermissions($username, self::$DEFAULT_USERSTATE);
			return self::$DEFAULT_USERSTATE;
		}
	}
	
	public function cmdEvent($d, $e){
		$cmd = $d["cmd"];
		$issuer = $d["issuer"];
		if($issuer instanceof Player && ($cmd == "tell" || $cmd == "me")){
			$username = $issuer->iusername;
			if(!$this->hasPermissions($username)) return false;
		}
	}
	
	public function punchMob($data, $event){
		$entity = $this->api->entity->get($data['eid']);
		if($entity instanceof Entity && $entity->isPlayer()){
			$username = $entity->player->iusername;
			if(!$this->hasPermissions($username)) return false;
		}
	}
	
	public function blockPlace($data){
		return $this->blockAction($data);
	}
	public function blockBreak($data){
		return $this->blockAction($data);
	}
	public function blockTouch($data){
		if($data["type"] == "break" && !self::$ALLOW_BLOCK_DESTRUCTION){
			return $this->blockAction($data);
		}
		if($data["type"] == "place" && !self::$ALLOW_BLOCK_PLACEMENT){
			return $this->blockAction($data);
		}
	}
	public function playerChat($data){
		return $this->blockAction($data); 
	}

	public function blockAction($data) {
		$username = $data['player']->iusername;
		if(!$this->hasPermissions($username)) {
			$data['player']->sendChat(self::$NO_PERMISSIONS);
			return false;
		}
	}
	
	public function addint($cmd, $params, $issuer, $alias){
		if(!isset($params[0])) return "Usage: /$cmd <nickname> [on|off]";
		$player = strtolower($params[0]);
		
		if(!isset($params[1])){
			return $this->hasPermissions($player) ? "$player can interact with the server." : "$player cannot interact with the server.";
		}
		$action = false;
		switch($params[1]){
			case "on":
				$action = true;
				break;
			case "off":
				$action = false;
				break;
			default:
				return "Usage: /$cmd <nickname> [on|off]";
		}
		
		$this->setPermissions($player, $action);
		$target = $this->api->player->get($player, false);
		
		if($target instanceof Player){
			$target->sendChat($action ? self::$GRANTED : self::$REVOKED);
		}
		return $action ? "'$player' is now able to interact with the server." : "'$player' is not able to interact with the server anymore.";
	}
	
	public function __destruct(){}
}
