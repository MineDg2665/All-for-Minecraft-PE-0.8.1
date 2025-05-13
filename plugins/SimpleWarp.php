<?php
/*
__PocketMine Plugin__
 name=SimpleWarp
 description=Simple plugin to make warps
 version=0.2.3_01
 author=Falk, GameHerobrine
 class=simpleWarp
 apiversion=12.1
 */
class simpleWarp implements Plugin {
	private $api, $path;
	public function __construct(ServerAPI $api, $server = false) {
		$this->api = $api;
	}

	public function init() {

		$this->api->console->register("addwarp", "Create a new warp", array($this, "command"));
		$this->api->console->register("delwarp", "Delete a warp", array($this, "command"));
		$this->api->console->register("warp", "Warp to a location", array($this, "command"));
		$this->api->console->register("openwarp", "Make a warp open to everyone", array($this, "command"));
		$this->api->console->register("closewarp", "Make warp OPS only", array($this, "command"));

		$this->config = new Config($this->api->plugin->configPath($this)."warps.yml", CONFIG_YAML, array());
		$this->api->ban->cmdWhitelist("warp");
		console("[INFO] SimpleWarp Loaded!");
	}

	public function __destruct() {
	}
	public function command($cmd, $params, $issuer, $alias) {
		switch ($cmd) {
			case "addwarp": 
			if (!($issuer instanceof Player)) return "Please run this command in game";
			if (isset($params[0])) {
				$data = $this->api->plugin->readYAML($this->api->plugin->configPath($this). "warps.yml");
				if (array_key_exists($params[0], $data)) return "[SimpleWarp] Warp exists with that name!";
				else {
					$x = round($issuer->entity->x);
					$y = round($issuer->entity->y);
					$z = round($issuer->entity->z);
					$level = $issuer->level->getName();
					$data[$params[0]] = array($x, $y, $z, $level);

					$this->api->plugin->writeYAML($this->api->plugin->configPath($this)."warps.yml", $data);
					return "[SimpleWarp] Warp Added!";
				}
			}
			else return "Usage: /addwarp <NAME>";

			break;
		case "delwarp": 
			if (isset($params[0])) {
				$data = $this->api->plugin->readYAML($this->api->plugin->configPath($this). "warps.yml");
				if (array_key_exists($params[0], $data)) {
					unset($data[$params[0]]);
					$this->api->plugin->writeYAML($this->api->plugin->configPath($this)."warps.yml", $data);
					return "[SimpleWarp] Warp removed!";
				}
				else return "[SimpleWarp] Warp doesn't exist!";
			}
			else return "Usage: /delwarp <NAME>";
			break;
		case "warp": 
			$data = $this->api->plugin->readYAML($this->api->plugin->configPath($this). "warps.yml"); 
			if (isset($params[0]) && $issuer instanceof Player) {
				if (array_key_exists($params[0], $data)) {
					$x = $data[$params[0]][0];
					$y = $data[$params[0]][1];
					$z = $data[$params[0]][2];
					$status = $data[$params[0]][4] ?? false;
					if (($level = $this->api->level->get($data[$params[0]][3])) === false) return "[SimpleWarp] Warp level is not loaded";
					if ($status === true) {
						$issuer->teleport(new Position($x, $y, $z, $level));
						return "[SimpleWarp] You have been warped to " . $params[0];
					}
					else {
						if ($this->api->ban->isOP($issuer->username) === true) {
							$issuer->teleport(new Position($x, $y, $z, $level));
							return "[SimpleWarp] You have been warped to " . $params[0];
						}
						else return "[SimpleWarp] Warp is private";
					}
				}
				else return "[SimpleWarp] Warp doesn't exist!";
			}
			else{
				if(count($data) == 0) return "[SimpleWarp] No warps found.";
				$ret = "Warp list: ";
				foreach ($data as $n => $more) $ret .= $n . ", ";
				return substr($ret,0,strlen($ret)-2);
			}
			break;
		case "openwarp": 
			if (isset($params[0])) {
				$data = $this->api->plugin->readYAML($this->api->plugin->configPath($this). "warps.yml");
				if (array_key_exists($params[0], $data)) {
					$data[$params[0]][] = true;
					$this->api->plugin->writeYAML($this->api->plugin->configPath($this)."warps.yml", $data);
					return "[SimpleWarp] Warp Opened!";
				}
				else return "[SimpleWarp] Warp doesn't exist!";
			}
			else return "Usage: /openwarp <NAME>";
			break;
		case "closewarp": 
			if (isset($params[0])) {
				$data = $this->api->plugin->readYAML($this->api->plugin->configPath($this). "warps.yml");
				if (array_key_exists($params[0], $data)) {
					unset($data[$params[0]][4]);
					$this->api->plugin->writeYAML($this->api->plugin->configPath($this)."warps.yml", $data);
					return "[SimpleWarp] Warp Closed";
				}
				else return "[SimpleWarps] Warp doesn't exist!";
			}
			else return "Usage: /closewarp <NAME>";
			break;
		}
	}
}
