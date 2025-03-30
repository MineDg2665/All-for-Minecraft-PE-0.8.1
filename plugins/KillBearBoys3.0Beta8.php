<?php

/*
 __PocketMine Plugin__
name=KillBearBoys
description=Better KillBearBoys
version=3.0beta8
author=ljy\Tema1d\GameHerobrine
class=KillBearBoys
apiversion=12.1
*/

const KBB = '[KillBearBoys] ';
const BLOCK_PLACE = 0;
const BLOCK_BREAK = 1;
const BLOCK_TOUCH = 2;

class KillBearBoys implements Plugin{
	public $api;
	public $database;
	public $user;
	public $temp;
	public $fetch;
	public $num;
	public $position;
	public $queue;
	public $runnable;
	public $lock;
	public static $act = array(
		0 => 'place',
		1 => 'break',
		2 => 'touch'
	);
	public $caughtWarn = false;
	private $executeGCEvery = 20;
	private $executed = 0;
	public function __construct(ServerAPI $api, $server = false){ 
		$this->api = $api;
	}
	private $coloumns = ["id" => [], "name" => [], "action" => [], "blockname" => [], "blockId" => [], "meta" => [], "level" => [], "x" => [], "y" => [], "z" => [], "time" => [], "item" => ["NUMERIC"], "rollbacked" => ["NUMERIC"]];
	
	private $wandID = 292;
	private $wandUsedBy = [];
	private $rollbackPositions = [];
	
	public function init(){
		date_default_timezone_set("Europe/Kiev"); //TODO configurable timezone, def: UTC
		$this->user = [];
		$this->temp = array();
		$this->queue = array();
		$this->fetch = false;
		$this->lock = false;
		$this->position = array();
		$path = $this->api->plugin->configPath($this)."DateBase.db";
		$path = str_replace("\\", "/", $path);
		if(!file_exists($path)){
			$this->database = new SQLite3($path);
			$this->query("CREATE TABLE logs (
				id INTEGER PRIMARY KEY, 
				name TEXT, 
				action NUMERIC, 
				blockname TEXT, 
				blockId NUMERIC, 
				meta NUMERIC, 
				level TEXT, 
				x NUMERIC, 
				y NUMERIC, 
				z NUMERIC,
				time TEXT,
				item NUMERIC,
				rollbacked NUMERIC
				);");
		}
		else{
			$this->database = new SQLite3($path);
		}
		
		$res = $this->query("PRAGMA table_info('logs');");
		foreach($this->coloumns as $c => $v){
			$a = $res->fetchArray(SQLITE3_NUM);
			if($a == false || $a[1] != $c){
				$this->query("ALTER TABLE logs ADD $c {$v[0]}");
			}
		}
		

		$cfg = new Config($this->api->plugin->configPath($this)."config.properties", CONFIG_PROPERTIES, ["wand-itemid" => IRON_HOE]);
		$this->wandID = (int)$cfg->get("wand-itemid");
		
		$this->query("PRAGMA journal_mode = OFF;");
		$this->query("PRAGMA synchronous = OFF;");
		$this->api->console->register("co", "get logs", array($this, "handleCommand"));
		$this->api->console->register("rb", "<player> <time>|all", array($this, "rollbackCommand")); //TOOD alias
		$this->api->console->register("rbpos", "<1|2>", array($this, "rbPos"));
		$this->api->console->alias("rbpos1", "rbpos 1");
		$this->api->console->alias("rbpos2", "rbpos 2");
		$this->api->console->register("unrb", "", array($this, "unrollbackCommand"));
		$this->api->console->register("rbwand", "", array($this, "rbWandCommand"));
		$this->api->addHandler("player.block.break", array($this, "blockactionHandler"), 15); 
		$this->api->addHandler("player.block.place", array($this, "blockactionHandler"), 15); 
		$this->api->addHandler("player.block.touch", array($this, "blockactionHandler"), 15);
		$this->api->addHandler("player.quit", array($this, "eventHandler"), 15);
		$this->api->addHandler("player.join", array($this, "eventHandler"), 15);
		$this->runnable = false; 
		$this->api->schedule(1, array($this, "queueSchedule"), NULL, true);
	}
	public function rbPos($cmd, $arg, $issuer, $alias){
		if(!($issuer instanceof Player)) return "Only players can use this command.";
		switch(nullsafe($arg[0], "")){
			case "1":
				$r = new Position($issuer->entity->round(), 0, 0, $issuer->entity->level);
				$this->rollbackPositions[$issuer->iusername][0] = $r;
				return "First Position is set to {$r->x} {$r->y} {$r->z} {$r->level->getName()}";
			case "2":
				$r = new Position($issuer->entity->round(), 0, 0, $issuer->entity->level);
				$this->rollbackPositions[$issuer->iusername][1] = $r;
				return "Second Position is set to {$r->x} {$r->y} {$r->z} {$r->level->getName()}";
			default:
				return "Invalid Command Usage";
		}
	}
	public function rbWandCommand($cmd, $arg, $issuer, $alias){
		if(!($issuer instanceof Player)) return "Only players can use this command.";
		if(isset($this->wandUsedBy[$issuer->iusername])){
			unset($this->wandUsedBy[$issuer->iusername]);
			unset($this->rollbackPositions[$issuer->iusername]);
			return "Rollback Wand Disabled.";
		}
		$this->wandUsedBy[$issuer->iusername] = true;
		//if(($issuer->gamemode & 0x01) === 0) $issuer->addItem($this->wandID, 0, 1);
		return "Rollback Wand Enabled.";
	}
	public function duration2sec($duration){
		$tms = $type = $time = []; //To avoid eclipse warning, not neccessary
		preg_match_all("/\d+[a-z]+/", $duration, $tms);
		$total = 0;
		foreach($tms[0] as $tm){
			preg_match("/[a-z]+/", $tm, $type); //get letters
			preg_match("/[0-9]+/", $tm, $time); //get numbers
			switch($type[0]){
				case "w":
				case "week":
				case "weeks":
					$total += $time[0] * 604800;
					break;
				case "d":
				case "day":
				case "days":
					$total += $time[0] * 86400;
					break;
				case "h":
				case "hour":
				case "hours":
					$total += $time[0] * 3600;
					break;
				case "m":
				case "min":
				case "mins":
				case "minutes":
				case "minute":
					$total += $time[0] * 60;
					break;
				case "s":
				case "sec":
				case "secs":
				case "seconds":
				case "second":
					$total += $time[0];
					break;
			}
		}
		return $total;
	}
	public function unrollbackCommand($cmd, $args, $issuer, $alias){ 
		$selPos1 = nullsafe($this->rollbackPositions[$issuer->iusername][0], false);
		$selPos2 = nullsafe($this->rollbackPositions[$issuer->iusername][1], false);
		if($selPos1 === false || $selPos2 === false){
			return "Please select both positions.";
		}
		if($selPos1->level != $selPos2->level){
			unset($this->rollbackPositions[$issuer->iusername]);
			return "Selection Positions were set in different worlds, aborting.";
		}
		$level = $selPos1->level;
		$startX = min($selPos1->x, $selPos2->x);
		$endX = max($selPos1->x, $selPos2->x);
		$startY = min($selPos1->y, $selPos2->y);
		$endY = max($selPos1->y, $selPos2->y);
		$startZ = min($selPos1->z, $selPos2->z);
		$endZ = max($selPos1->z, $selPos2->z);
		$fetch = $this->query("SELECT * FROM logs WHERE level='{$level->getName()}'  
		AND x<='$endX' AND x>='$startX' 
		AND y<='$endY' AND y>='$startY' 
		AND z<='$endZ AND z>=$startZ' 
		AND action!=2 AND rollbacked=1 
		ORDER BY id DESC");
		if(!($fetch instanceof SQLite3Result)){
			return "No logs were found.";
		}
		while(($dat = $fetch->fetchArray(SQLITE3_ASSOC)) !== false){
			$act = $dat["action"];
			$x = $dat["x"];
			$y = $dat["y"];
			$z = $dat["z"];
			$id = $dat["blockId"];
			$meta = $dat["meta"];
			$level->setBlock(new Vector3($x, $y, $z), BlockAPI::get($act === 0 ? $id : 0, $act === 0 ? $meta : 0));
		}
		
		$this->query("UPDATE logs SET rollbacked=0 WHERE level='{$level->getName()}'  
		AND x<='$endX' AND x>='$startX' 
		AND y<='$endY' AND y>='$startY' 
		AND z<='$endZ AND z>=$startZ'
		AND action!=2");
		
		return "Unrollback done";
	}
	public function rollbackCommand($cmd, $args, $issuer, $alias){ 
		if(!($issuer instanceof Player)) return "Only players can use this command.";
		if(!isset($args[0])) return "Please enter players's nickname";
		if(!isset($args[1])) return "Please enter rollback time(1h2m3s) or all";
		$tstmp = microtime(true);
		$nick = $args[0];
		$time = $args[1] === "all" ? $tstmp : $this->duration2sec($args[1]);
		$afttstmp = $tstmp - $time;
		$selPos1 = nullsafe($this->rollbackPositions[$issuer->iusername][0], false);
		$selPos2 = nullsafe($this->rollbackPositions[$issuer->iusername][1], false);
		if($selPos1 === false || $selPos2 === false){
			return "Please select both positions.";
		}
		if($selPos1->level != $selPos2->level){
			unset($this->rollbackPositions[$issuer->iusername]);
			return "Selection Positions were set in different levels, aborting.";
		}
		$level = $selPos1->level;
		$startX = min($selPos1->x, $selPos2->x);
		$endX = max($selPos1->x, $selPos2->x);
		$startY = min($selPos1->y, $selPos2->y);
		$endY = max($selPos1->y, $selPos2->y);
		$startZ = min($selPos1->z, $selPos2->z);
		$endZ = max($selPos1->z, $selPos2->z);
		
		$fetch = $this->query("SELECT * FROM logs WHERE name='$nick' AND level='{$level->getName()}'  
		AND x<='$endX' AND x>='$startX' 
		AND y<='$endY' AND y>='$startY' 
		AND z<='$endZ AND z>=$startZ'
		AND action!=2 AND time>=$afttstmp
		ORDER BY id DESC");
		
		if(!($fetch instanceof SQLite3Result)){
			return "No logs were found.";
		}
		while(($dat = $fetch->fetchArray(SQLITE3_ASSOC)) !== false){
			$act = $dat["action"];
			$x = $dat["x"];
			$y = $dat["y"];
			$z = $dat["z"];
			$id = $dat["blockId"];
			$meta = $dat["meta"];
			$level->setBlock(new Vector3($x, $y, $z), BlockAPI::get($act === 0 ? 0 : $id, $act === 0 ? 0 : $meta));
		}
		
		$this->query("UPDATE logs SET rollbacked=1 WHERE name='$nick' AND level='{$level->getName()}'  
		AND x<='$endX' AND x>='$startX' 
		AND y<='$endY' AND y>='$startY' 
		AND z<='$endZ AND z>=$startZ'
		AND action!=2 AND time>=$afttstmp");
		
		return "Rollback done";
	}
	public function eventHandler($data, $event){
		switch($event){
			case "player.quit":
			case "player.join":
				if(isset($this->user[$data->iusername])){
					unset($this->user[$data->iusername]);
					unset($this->rollbackPositions[$data->iusername]);
					unset($this->wandUsedBy[$data->iusername]);
				}
				break;
		}
	}
	public function queueSchedule(){
		if($this->lock) return;
		if($this->runnable){
			return;
		}
		else{
			$this->runnable = true;
		}
		if(count($this->queue) > 0){
			$q = array_shift($this->queue);
			if($q[1]){
				$data = $this->query($q[0]);
				while(($dat = $data->fetchArray(SQLITE3_ASSOC)) !== false){
					$this->fetch[] = $this->arr2str($dat);
				}
			}
			else{
				$this->query($q[0]);
			}
		}
		$this->runnable = false;
	}
	
	public function addQueue($sql, $fetch = false){
		$this->queue[] = array($sql, $fetch);
	}
	
	public function lockQueue(){
		$this->lock = true;
	}
	
	public function unlockQueue(){
		$this->lock = false;
	}
	
	public function blockactionHandler($data, $event){
		$p = $data["player"];
		if(isset($this->wandUsedBy[$p->iusername]) && $data["item"]->getID() === $this->wandID){
			if($event === "player.block.touch"){
				$r = new Position($data["target"]->round(), 0, 0, $data["target"]->level);
				switch($data["type"]){
					case "break":
						$this->rollbackPositions[$p->iusername][0] = $r;
						$p->sendChat("First Position is set to {$r->x} {$r->y} {$r->z} {$r->level->getName()}");
						return false;
					default:
						$this->rollbackPositions[$p->iusername][1] = $r;
						$p->sendChat("Second Position is set to {$r->x} {$r->y} {$r->z} {$r->level->getName()}");
						return false;
				}
			}
			return false;
		}
			
		if(isset($this->user[$p->iusername])){
			$this->getLog($data, $event, false);
			return false;
		}
		else{
			$this->addLog($data, $event);
		}
	}
	
	public function getLog($data, $event, $output = true){
		switch($event){
			case "player.block.place":
				$target = $data["block"];
				break;
			case "player.block.break":
			case "player.block.touch":
				$target = $data["target"];
				break;
		}
		$level = $data['player']->entity->level->getName();
		$x = $target->x;
		$y = $target->y;
		$z = $target->z;
		$position = array($level, $x, $y, $z);
		$this->lockQueue();
		$query = "SELECT * FROM logs WHERE level='".$level."' AND x='".$x."' AND y='".$y."' AND z='".$z."' AND (rollbacked = 0 OR rollbacked IS NULL) ORDER BY id DESC";
		
		if($output){
			$this->outputQuery($query);
			$this->unlockQueue();
			return;
		}
		
		$count = count($this->temp);
		if($this->position == $position){
			if($this->num >= ($count - 1)){
				$this->num = 0;
			}
			else{
				$this->num++;
			}
		}
		else{
			$this->position = $position;
			$this->temp = array();
			$this->num = 0;
			$fetch = $this->query($query);
			if(!($fetch instanceof SQLite3Result)){
				$result = KBB.'No logs here or server is too busy.';
				$data["player"]->sendChat($result);
				$this->unlockQueue();
				return;
			}
			while(($dat = $fetch->fetchArray(SQLITE3_ASSOC)) !== false){
				$this->temp[] = $dat;
			}
			$fetch->finalize();
			$count = count($this->temp);
		}
		
		if($count == 0){
			$result = KBB.'No logs here or server is too busy.';
		}
		else{
			$result = KBB.'['.($count - $this->num).'/'.$count.'] [x:'.$x.'][y:'.$y.'][z:'.$z."]\n";
			$result = $result.$this->arr2str($this->temp[$this->num]);
		}
		
		$data["player"]->sendChat($result);
		$this->unlockQueue();
	}
	
	public function addLog($data, $event){
		$needsLog = true;
		switch($event){
			case "player.block.place":
				$target = $data["block"];
				$block = $data["item"];
				$action = BLOCK_PLACE;
				break;
			case "player.block.break":
				$target = $data["target"];
				$block = $data["target"];
				$action = BLOCK_BREAK;
				break;
		    case "player.block.touch":
				$target = $data["target"];
				$block = $data["target"];
				$item = $data["item"];
				$action = BLOCK_TOUCH;
				if(($block->getID() != CHEST && $block->getID() != FURNACE) && ($item->getID() != BUCKET && $item->getID() != WATER && $item->getID() != STILL_WATER)){
					$needsLog = false;
				}
				break;
		}
		if($needsLog){
			$name = $data["player"]->iusername;
			$blockname = $block->getName();
			$blockId = $block->getID();
			$meta = $block->getMetadata();
			$level = $data["player"]->entity->level->getName();
			$x = $target->x;
			$y = $target->y;
			$z = $target->z;
			$query = "INSERT INTO logs VALUES (NULL, '$name', $action, \"$blockname\", $blockId, $meta, '$level', $x, $y, $z, ".time().", {$data["item"]->getID()}, 0);";

			$this->addQueue($query);
		}
		$this->executed++;
		if($this->executed >= $this->executeGCEvery){
			gc_collect_cycles();
			$this->executed = 0;
		}
	}
	public function handleCommand($cmd, $arg, $issuer, $alias){ 
		switch($cmd){
			case "co":
				if(isset($this->user[$issuer->iusername])){
					unset($this->user[$issuer->iusername]);
					return (KBB.'Disabled.');
				}
				else{
					$this->user[$issuer->iusername] = true;
					return (KBB.'Enabled.');
				}
				break;
		}
	}
	
	public function arr2str($arr){
		$date = date("D, j/m/Y H:i:s", $arr["time"]);
		$itemid = isset($arr["item"]) ? $arr["item"] : -1;
		$itemname = $itemid === -1 ? "Unknown" : BlockAPI::getItem($itemid)->getName();
		$str = KBB."BlockID: ".$arr["blockId"].":".$arr["meta"].  " (".$arr["blockname"].")"." ItemID: $itemid ($itemname)"."\n";
		$str = $str.KBB."The player who ".self::$act[$arr["action"]]." this block: ".$arr["name"]."\n";
		$interval = round((time() - $arr["time"]) / 3600, 2);
		$str = $str.KBB."Time: ".$interval." hour(s) ago, " .$date;
		return $str;
	}
	
	public function act2str($action){
		return $this->act[$action];
	}
	
	public function pos2str(Position $pos){
		return "Position(level=".$pos->level->getName().",x=".ceil($pos->x).",y=".ceil($pos->y).",z=".ceil($pos->z).")";
	}
	
	public function outputQuery($query){
		$data = $this->query($query);
		
		while(($dat = $data->fetchArray(SQLITE3_ASSOC)) !== false){
			console($this->arr2str($dat));
		}
		console(KBB.'NULL');
		$data->finalize();
	}
	
	public function test(){
		$this->lockQueue();
		for($i = 1; $i <= 1000; $i++){
			$this->addQueue("INSERT INTO logs VALUES (NULL, 'ljy', ".BLOCK_BREAK.", 'Stone', 1, 0, 'world', 100, 100, 100, ".time().");");
		}
		$this->unlockQueue();
	}
	public function query($sql, $fetch = false){
		$result = $this->database->query($sql) or console("[ERROR] [SQL Error] " . $this->database->lastErrorMsg() . ". Query: " . $sql, true, true, 0);
		if($fetch === true and ($result instanceof SQLite3Result)){
			$result = $result->fetchArray(SQLITE3_ASSOC);
		}
		return $result;
	}
	
	public function save(){
		$this->database->close();
	}
	
	public function __destruct(){
		$this->save();
	}
}
