<?php

/*
__PocketMine Plugin__
name=Minecarts
description=gives minecart.
version=1.0.0
author=tema1d
class=MinecartGive
apiversion=12.1
*/

class MinecartGive implements Plugin{
	private $api, $sessions, $path, $config;
	public function __construct(ServerAPI $api, $server = false){
		$this->api = $api;
		$this->sessions = array();
	}
	
	public function init(){
	    $this->api->console->register("minecart", "Get free minecart.", array($this, "command"));
	    $this->api->ban->cmdwhitelist("minecart");
		$this->path = $this->api->plugin->configPath($this);
		$this->config = new Config($this->path."config.yml", CONFIG_YAML, array(
			"GiveMinecart" => MINECART,
		));
	}
	public function __destruct(){
	}
	
	public function command($cmd, $params, $issuer, $alias){
		$output = "";
		if($alias !== false){
			$cmd = $alias;
		}
		if($cmd[0] === "/"){
			$cmd = substr($cmd, 1);
		}
		
		switch($cmd){
		    case "minecart":
				if(!($issuer instanceof Player)){
					$output .= "Please run this command in-game.\n";
					break;
				}
				if($issuer->gamemode === CREATIVE){
					$output .= "You are creative.\n";
				}else{
					$this->api->entity->drop(new Position($issuer->entity->x - 0.5, $issuer->entity->y, $issuer->entity->z - 0.5, $issuer->entity->level), BlockAPI::getItem($this->config->get("GiveMinecart")));
				}
				$output .= "You received a free minecart.";
				break;
				default:
				break;
		}
		return $output;
	}
}