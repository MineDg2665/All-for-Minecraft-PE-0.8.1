<?php
/*
__PocketMine Plugin__
name=NPEKeepInventory
description=A KeepInventory from NPECore
version=1.0
author=GameHerobrine
class=NPEKeepInventory
apiversion=12.1,13.1
*/
class NPEKeepInventory implements Plugin{
	public $api;
	public function __construct(ServerAPI $api, $s = false){
		$this->api = $api;
	}
	
	public function init(){
		$this->api->addHandler("entity.death", [$this, "onDeath"]);
		$this->api->addHandler("player.respawn", [$this, "onRespawn"]);
	}
	
	public function onRespawn($d, $e){
		$d->entity->fire = 0;
		$d->entity->updateMetadata();
	}
	
	public function onDeath($d, $e){
		$entity = $d["entity"];
		
		if($entity instanceof Entity && $entity->isPlayer()){
			$pk = new \SetHealthPacket();
			$pk->health = $entity->health;
			$entity->player->dataPacket($pk);
			$entity->fire = 0;
			$entity->air = 200;
			$entity->crouched = false;
			$entity->fallY = false;
			$entity->fallStart = false;
			$entity->updateMetadata();
			$entity->dead = true;
			$pk = new \MoveEntityPacket_PosRot();
			$pk->eid = $entity->eid;
			$pk->x = -256;
			$pk->y = 128;
			$pk->z = -256;
			$pk->yaw = 0;
			$pk->pitch = 0;
			$plz = $entity->level->players;
			unset($plz[$entity->player->CID]);
			$this->api->player->broadcastPacket($plz, $pk);
			$entity->player->lastCorrect = new \Vector3($entity->x, $entity->y, $entity->z);
			
			$this->api->dhandle("player.death", [
				"player" => $entity->player,
				"cause" => $d["cause"]
			]);
			$entity->player->setSpawn($entity->level->getSafeSpawn()->add(0, 1, 0));
			return false;
		}
	}
}