<?php

/*
__PocketMine Plugin__
name=UChat
description=UChat
version=2.10
author=1ron_pon3
class=UChat
apiversion=11
*/


class UChat implements Plugin{
	private $api, $prefix, $path, $user;
	
	public function __construct(ServerAPI $api, $server = false){
		$this->api = $api;
	}
	
	public function init(){
		$this->api->addHandler("player.join", array($this, "handler"), 5);
		$this->api->addHandler("player.chat", array($this, "handler"), 5);
		$this->api->addHandler("player.death", array($this, "handler"), 5);
		$this->api->addHandler("player.quit", array($this, "handler"), 5);		
		$this->readConfig();
		$this->api->console->register("setprefix", "Change User's Prefix", array($this, "Pref"));
		$this->api->console->register("defprefix", "Set default Prefix", array($this, "Pref"));
		$this->api->console->register("delprefix", "Delete prefix", array($this, "Pref"));
		$this->api->console->register("setnick", "Set user's nickname", array($this, "Pref"));
		$this->api->console->register("delnick", "Remove user's nickname", array($this, "Pref"));
		$this->api->console->register("mute", "Mute player", array($this, "Pref"));
		$this->api->console->register("unmute", "Unmute player", array($this, "Pref"));
		$this->api->console->register("uce", "Enable chat", array($this, "Pref"));
		$this->api->console->register("ucd", "Disable chat", array($this, "Pref"));
		console(FORMAT_GREEN."[UChat] Loaded configuration!");
		
	}
	
	public function __destruct(){
	}
	
	public function readConfig(){
		$this->path = $this->api->plugin->createConfig($this, array(
			"chat-format" => "{WORLDNAME}:[{prefix}]<{DISPLAYNAME} ({kills})> {MESSAGE}",
			"default" => "Player",
			"save-kills" => true,
			"chat" => "enable",
		));
		$this->config = $this->api->plugin->readYAML($this->path."config.yml");
		if(!file_exists($this->path."kills.yml")){
        $c = new Config($this->path."kills.yml", CONFIG_YAML, array());}
        $this->killconfig = $this->api->plugin->readYAML($this->path."kills.yml");
	}

	
	public function Pref($cmd, $args){
	switch($cmd){
	    case "setprefix":
	      $player = $args[0];
	      $pref = $args[1];
	      
	      $this->config['player'][$player]['pref'] =$pref;
	      $this->api->plugin->writeYAML($this->path."config.yml", $this->config);
	      
	      $output .= "[UChat] Gived ".$pref." to ".$player.".\n";
	      console(FORMAT_GREEN."[UChat] Gived ".$pref." to ".$player.".");
	      $this->api->chat->sendTo(false, "[UChat] Your prefix is now ".$pref." !", $player);
      break;
	    case "defprefix":
	      $def = $args[0];
	       
	      $this->config['default']=$def;
	      $this->api->plugin->writeYAML($this->path."config.yml", $this->config);
	       
	      $output .= "[UChat] Default prefix is now ".$def.".\n";
	    break;
	    case "delprefix":
	      $player = $args[0];
	       
	      unset($this->config['player'][$player]['pref']);
	      $this->api->plugin->writeYAML($this->path."config.yml", $this->config);
	       
	      $output .= "[UChat] Deleted".$player."'s  prefix.\n";
	      $this->api->chat->sendTo(false, "[UChat] Your prefix is now default!", $player);
	    break;
	    case "setnick":
	      $player = $args[0];
	      $nick = $args[1];
	      
	      $this->config['player'][$player]['nick'] = "~".$nick;
	      $this->api->plugin->writeYAML($this->path."config.yml", $this->config);
	      
	      $output .= "[UChat] Gived ".$nick." to ".$player.".\n";
	      console(FORMAT_GREEN."[UChat] Gived ".$nick." to ".$player.".");
	      $this->api->chat->sendTo(false, "[UChat] Your nick is now ".$nick." !", $player);
      break;
      case "delnick":
	      $player = $args[0];
	      
	      unset($this->config['player'][$player]['nick']);
	      $this->api->plugin->writeYAML($this->path."config.yml", $this->config);
	      
	      $output .= "[UChat] ".$player."'s name is now real.\n";
	      console(FORMAT_GREEN."[UChat] ".$player."'s name is now real.\n");
	      $this->api->chat->sendTo(false, "[UChat] Your name is now real!", $player);
      break;
      case "mute":
	      $player = $args[0];
	      
	      $this->config['player'][$player]['mute'] = true;
	      $this->api->plugin->writeYAML($this->path."config.yml", $this->config);
	      
	      $output .= "[UChat] ".$player." is now muted from chat.\n";
	      console(FORMAT_GREEN."[UChat] ".$player." is now muted from chat.\n");
	      $this->api->chat->sendTo(false, "[UChat] You have been muted from chat!", $player);
      break;
      case "unmute":
	      $player = $args[0];
	      
	      unset($this->config['player'][$player]['mute']);
	      $this->api->plugin->writeYAML($this->path."config.yml", $this->config);
	      
	      $output .= "[UChat] ".$player." is now unmuted from chat\n";
	      console(FORMAT_GREEN."[UChat] ".$player." is now unmuted from chat\n");
	      $this->api->chat->sendTo(false, "[UChat] You have been unmuted from chat!", $player);
      break;
	  case "uce":
	      $this->config['chat']="enable";
		  $output .= "[UChat] Chat is now enabled\n";
	  break;
	  case "ucd":
	      $this->config['chat']="disable";
		  $output .= "[UChat] Chat is now disabled\n";
	  break;
      default:		$output .= '[UChat] UChat by 1ron_pon3';
      break;
	  }
	  return $output;
	  }
	  
	public function handler(&$data, $event){
		switch($event){
		    case "player.death":
                $player = $this->api->entity->get($data["cause"]);
			    if($player instanceof Entity){
			    $player = $player->name; }
				If(!isset($this->killconfig['player'][$player]['kills'])){
				$this->killconfig['player'][$player]['kills']=1;
				}
				else {
				$this->killconfig['player'][$player]['kills']=$this->killconfig['player'][$player]['kills']+1;
				}
				break;
			case "player.quit":
			If($this->config['save-kills']==true){
			    $this->api->plugin->writeYAML($this->path."kills.yml", $this->killconfig);
				console(FORMAT_GREEN."[UChat] Saved kills!"); }
			    break;
			case "player.chat":
          $player = $data["player"]->username;
		  If(!isset($this->config['player'][$player]['mute']) && $this->config['chat']=="enable")
		  {
		     If(!isset($this->config['player'][$player]['pref'])){
		     $prefix=$this->config['default'];
		     }
		     else{
		     $prefix= $this->config['player'][$player]['pref'];
		     }
		     If(!isset($this->config['player'][$player]['nick'])){
		     $nickname=$player;
		     }
		     else{
		     $nickname=$this->config['player'][$player]['nick'];
		     }
			 If(!isset($this->killconfig['player'][$player]['kills'])){
			 $kills=0;
			 }
			 else {
			 $kills=$this->killconfig['player'][$player]['kills'];
			 }
		    
          $data = array("player" => $data["player"], "message" => str_replace(array("{DISPLAYNAME}", "{MESSAGE}", "{WORLDNAME}", "{prefix}", "{kills}"), array($nickname, $data["message"], $data["player"]->level->getName(), $prefix, $kills), $this->config["chat-format"]));
          if($this->api->handle("UChat.".$event, $data) !== false){
					  $this->api->chat->broadcast($data["message"]);
				 }
				 return false;
		  }
		   elseif(isset($this->config['player'][$player]['mute']))
		   {
		   $this->api->chat->sendTo(false, "[UChat] You have been muted from chat!", $player);
		   return false;
		   }
		   else
		   {
		   $this->api->chat->sendTo(false, "[UChat] Chat is now disabled!", $player);
		   return false;
		   }
			break;
		}
	}	
}