<?php

/*
__PocketMine Plugin__
name=NetherSpecial
description=Makes pigman spawn in world with name "nether". The world must be preloaded!
version=1.0
author=GameHerobrine
class=net\skidcode\gh\netherspecial\NetherSpecial
apiversion=12.1
*/
namespace net\skidcode\gh\netherspecial{
	
	class PigmanSpawner extends \MobSpawner
	{
		public $server;
		
		public function __construct(\Level $level){
			parent::__construct($level);
			$this->server = \ServerAPI::request();
		}
		
		public function countEntities(){
			$cnt = 0;
			foreach($this->level->entityList as $e){
				$cnt += $e->type === MOB_PIGMAN;
			}
			return $cnt;
		}
		
		public function spawnMobs(){
			$x = mt_rand(0,255);
			$z = mt_rand(0,255);
			$y = $this->getSafeY($x, $z, false, true);
			
			if(!$y || $y < 0){
				return false;
			}
			$data = $this->genPosData($x, $y + 0.5, $z);
			
			$e = $this->server->api->entity->add($this->level, 2, MOB_PIGMAN, $data);
			if($e instanceof \Entity){
				$this->server->api->entity->spawnToAll($e);
				//console("[DEBUG] PIGMAN spawned at $x, $y, $z");
			}
			return true;
		}
		
		private function genPosData($x, $y, $z){
			return [
				"x" => $x + 0.5,
				"y" => $y,
				"z" => $z + 0.5
			];
		}
	}
	
	class NetherSpecial implements \Plugin{
		private $api;
		
		public function __construct(\ServerAPI $api, $server = false){
			$this->api = $api;
			$api->event("server.start", [$this, "postInit"]); //post init
		}
		
		public function postInit(){
			if(!$this->api->level->loadLevel("NETHER")){
				Logger::w("Nether world is not loaded!", $this);
			}else{
				$lvl = $this->api->level->get("NETHER");
				$lvl->mobSpawner = new PigmanSpawner($lvl);
			}
		}
		
		public function init(){
			
		}
		
		public function __destruct(){
		
		}
	}
}