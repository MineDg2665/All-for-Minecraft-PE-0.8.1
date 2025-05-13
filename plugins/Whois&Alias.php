<?php

/*
__PocketMine Plugin__
name=Whois&Alias.
description=Whois/Alias.
version=0.8.1/v2
author=DarkN3ss/tema1d.
class=WhAl
apiversion=12.1
*/

class WhAl implements Plugin{
   private $api;

   public function __construct(ServerAPI $api, $server = false){
     $this->api = $api;
     $this->server = ServerAPI::request();
   }

   public function init(){
       //alias
       $this->path = $this->api->plugin->configPath($this);
		if(!is_dir($this->path.'players/')) mkdir($this->path.'players/');
		$this->api->console->register("alias", "<username>", array($this, "cmdHandler"));
       //whois
		$this->api->console->register("whois", "<username>", array($this, "cmdHandler"));
		
		$this->api->addHandler("player.join", array($this, "join"));
   }
   
    public function join($data) {
    $name = $data->iusername;
    $ip = $data->ip;
    $filePath = $this->path . 'players/' . $name . '.txt';
    
    $file = new Config($filePath, CONFIG_LIST);
        $file->set($ip);
        $file->save();
}

   public function cmdHandler($cmd, $params, $issuer, $alias)
	{
		$user["issuer"] = strtolower($issuer instanceof Player ? $issuer->username : $issuer);
		$user["target"] = strtolower(isset($params[0]) ? $params[0] : "");
		switch($cmd) {
			case "whois":
				if(!$this->api->player->get($user['target']) instanceof Player)
				{ 
					return "Usage: /whois <nickname>"; 
				}
				else
				{
					$player = $this->api->player->get($user["target"]);
					$playerIP = $player->ip;
					if( ! filter_var($playerIP, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE) )
					{
						$result = $this->asyncOperation("http://ip-api.com/json/");
					}
					else
					{
						$result = $this->asyncOperation("http://ip-api.com/json/" . $playerIP);
					}
					$namee = $player->username;
					$country = json_decode($result, 1)['country'];
					$region = json_decode($result, 1)['regionName'];
					$city = json_decode($result, 1)['city'];
					return ("Nickname: " . $namee . "\nIP: " . $playerIP . "\nCountry, region, city: " . ucwords(strtolower($country)) . ", " . ucwords(strtolower($region)) . ", " . ucwords(strtolower($city)));
				}
				break;
				
				case "alias":
            if(!$this->api->player->get($user['target']) instanceof Player)
            { 
                return "Offline player."; 
            }
            else
            {
                $name = strtolower($params[0]);
                $player = $this->api->player->get($name);
                
                if ($player === false) {
                    $playerFile = DATA_PATH . 'players/' . $name . '.yml';

                    if (!file_exists($playerFile)) {
                        return "[Alias] Player doesn't exist";
                    }

                    $player = $this->api->player->getOffline($name);
                    $ip = $player->get('lastIP');
                } else {
                    $ip = $player->ip;
                }

                $filePath = $this->path . 'players/' . $name . '.txt';

                if (file_exists($filePath)) {
                    $file = new Config($filePath, CONFIG_LIST);
                    $ips = $file->getAll(true);
                    unset($ips['player_ips']);
                    $ips = implode(', ', $ips);

                    if ($issuer instanceof Player) {
                        $issuer->sendChat("[Alias] Showing aliases of " . $name);
                        $issuer->sendChat("[Alias] " . $ips);
                    } else {
                        console("[Alias] Showing aliases of " . $name);
                        console("[Alias] " . $ips);
                    }
                } else {
                    return '[Alias] No aliases found';
                }
            }
            break;
		}
		return;
	}
	
	public function asyncOperation($url){
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HEADER, false);
		$data = curl_exec($curl);
		curl_close($curl);
		return $data;
	}
   
	public function __destruct(){
	}
}
?>
