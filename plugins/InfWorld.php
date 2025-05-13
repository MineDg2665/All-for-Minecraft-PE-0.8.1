<?php
/*
__PocketMine Plugin__
 name=InfWorld
 description=Simple infinite world by loading a new world.
 version=1.4
 author=EkiFoX + Kran
 class=InfWorld
 apiversion=12.1
 */
class InfWorld implements Plugin {
    private $api;
    private static $path;
    private static $config;
    
    public function __construct(ServerAPI $api, $server = false) {
        $this->api = $api;
    }

    public function init() {
        self::$path = $this->api->plugin->configPath($this);
        if(!file_exists(self::$path."config.yml")){
            //create empty file
            file_put_contents(self::$path."config.yml", "");
        }
        self::$config = new Config(self::$path."config.yml", CONFIG_YAML);
		$this->api->addHandler("player.move", array($this, "move"));
		$this->api->addHandler("player.spawn", array($this, "spawn"));
		$this->api->console->register("infinite", "Information of infinite world plugin", array($this, "command"));
		$this->api->ban->cmdWhitelist("infinite");
		$this->api->console->register("infpos", "use /infpos to get your position", array($this, "command"));
		$this->api->ban->cmdWhitelist("infpos");
		console("[InfWorld] Loaded, version 1.3");
	}

	public function __destruct() {}

	public function spawn($data) {
        $player = $data;
		$world = $player->level->getName();
		$data->sendChat("[InfWorld] You are in map (".$world.")");
	}
	
	public function command($cmd, $params, $issuer) {
		switch ($cmd) {
			case "infinite":
				$issuer->sendChat("Plugin made by EkiFoX, modified by Kran");
				break;
			case "infpos":
                $yaw = $issuer->entity->yaw;
                if (($yaw >= -45) && ($yaw < 45)) {
                    $direction = "Z+";
                } elseif (($yaw >= 45) && ($yaw < 135)) {
                    $direction = "X-";
                } elseif (($yaw >= 135) || ($yaw < -135)) {
                    $direction = "Z-";
                } else {
                    $direction = "X+";
                }
                $issuer->sendChat("You are at (".intval($issuer->entity->x).",".intval($issuer->entity->y).",".intval($issuer->entity->z).") in map (".$issuer->level->getName().") facing ".$direction);
                break;
		}
	}
    
    public function getSafeZone($xs, $ys, $zs, $lvl){
    //Code from PocketMine-MP/src/world/Level.php 
            $x = (int)round($xs);
            $y = (int)round($ys);
            $z = (int)round($zs);
            $lvl = (string)$lvl;
            
        $world = $this->api->level->get($lvl);
        if ($world != false){
            for(; $y > 0; --$y){
                $v = new Vector3($x, $y, $z);
                $b = $world->getBlock($v);
                if($b === false){
                    return new Position($xs, $ys, $zs, $world);
                }elseif(!($b instanceof AirBlock)){
                    break;
                }
            }
            for(; $y < 128; ++$y){
                $v2 = new Vector3($x, $y-2, $z);
                $v = new Vector3($x, $y, $z);
                
                if($world->getBlock($v) instanceof AirBlock){
                    $v2b = $world->getBlock($v2);
                    if($v2b instanceof WaterBlock){
                        $world->setBlock(new Vector3($x, $y-1, $z), BlockAPI::get(ICE, 0));
                    }
                    return new Position($x, $y, $z, $world);
                }else{
                    ++$y;
                }
            }
            return new Position($x, $y, $z, $world);
        }else{
            console("Can't get a safe zone for teleport a player.");
            return false;
        }
    }

    private function clientRemoveEntities($player){
        //foreach($player->level->entityList as $entity){
        //    $entity->close();
        //}
    }
  
    public function move($data){
        $plobj = $this->api->player->get($data->name);
        $x = round($data->x);
        $z = round($data->z);
        $level = $plobj->level;
        $world = $level->getName();
        //check if name matches -*[0-9]+,-*[0-9]+
        if(!preg_match("/^-*[0-9]+,-*[0-9]+$/", $world)){
            $worldX = 0;
            $worldZ = 0;
        } else {
            list($worldX, $worldZ) = explode(",", $world);
        }
        $worldX = (int)$worldX;
        $worldZ = (int)$worldZ;
        if($x == 255){
            $newworld = ($worldX + 1) . "," . $worldZ;
            $this->changeWorld($plobj, $newworld, 2, $z);
            if(count($level->players) == 0){
                if($level != $this->api->level->getDefault()) $this->api->level->unloadLevel($level, true);
            }
        }
        if($x == 0){
            $newworld = ($worldX - 1) . "," . $worldZ;
            $this->changeWorld($plobj, $newworld, 254, $z);
            if(count($level->players) == 0){

		if($level != $this->api->level->getDefault()) $this->api->level->unloadLevel($level, true);
            }
        }
        if($z == 0){
            $newworld = $worldX . "," . ($worldZ - 1);
            $this->changeWorld($plobj, $newworld, $x, 254);
            if(count($level->players) == 0){
                if($level != $this->api->level->getDefault()) $this->api->level->unloadLevel($level, true);
            }
        }
        if($z == 255){
            $newworld = $worldX . "," . ($worldZ + 1);
            $this->changeWorld($plobj, $newworld, $x, 2);
            if(count($level->players) == 0){
                if($level != $this->api->level->getDefault()) $this->api->level->unloadLevel($level, true);
            }
        }
        return true;
    }

    private function changeWorld($plobj, $newworld, $x, $z){
        self::$config->set($plobj->username."Map", $newworld);
        //if($newworld == "0,0") {
        //    $plobj->sendChat("[InfWorld] GREAT CRASH FIX (can't go to 0,0)");
        //} else {
            if($this->api->level->loadLevel($newworld)){					
                $safe = $this->getSafeZone($x,128,$z,$newworld);
                if($safe != false){
                    self::clientRemoveEntities($plobj);
	                //$player->teleport($this->api->level->get($target_map)->getSafeSpawn());
                    $plobj->teleport($safe);
                    $plobj->entity->speedX = 0;//
                    $plobj->entity->speedY = 0;// attempt to fix "slingshot" bug
                    $plobj->entity->speedZ = 0;//
                    $plobj->sendChat("[InfWorld] You are in map (".$newworld.")");
                }else $plobj->sendChat("[InfWorld] Failed.");
            }else{
                $plobj->sendChat("[InfWorld] New map is generating...");
                $this->api->level->generateLevel($newworld);
            }
        //}
    }
}