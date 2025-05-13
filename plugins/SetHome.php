<?php
/*
__PocketMine Plugin__
name=Sethome
description=Set's a home for a player!
version=1.0
author=MineDg
class=SetHome
apiversion=12.1
*/

class SetHome implements Plugin {
    private $api;

    public function __construct(ServerAPI $api, $server = false) {
        $this->api = $api;
    }

    public function init() {
        $this->config = new Config($this->api->plugin->configPath($this) . "messages.yml", CONFIG_YAML, array(
            "sethome" => "Home set.",
            "no-home" => "You haven't set a home yet!",
            "home" => "Teleporting..."
        ));
        $this->homes = new Config($this->api->plugin->configPath($this) . "homes.yml", CONFIG_YAML);
        $this->api->console->register('sethome', "Sets your home to your position.", array($this, 'commandHandler'));
        $this->api->console->register('home', "Teleports you to your home.", array($this, 'commandHandler'));

        $this->api->ban->cmdWhitelist("sethome");
        $this->api->ban->cmdWhitelist("home");
    }

    public function commandHandler($cmd, $params, $issuer, $alias) {
        $user = $issuer->username;
        $level = $issuer->level->getName();
        
        switch ($cmd) {
            case 'sethome':
                $this->homes->set($user, array(
                    "x" => round($issuer->entity->x),
                    "y" => round($issuer->entity->y),
                    "z" => round($issuer->entity->z),
                    "level" => $level
                ));
                $issuer->sendChat($this->config->get("sethome"));
                $this->homes->save();
                break;

            case 'home':
                if ($this->homes->exists($user)) {
                    $poss = $this->homes->get($user);
                    $x = $poss["x"];
                    $y = $poss["y"];
                    $z = $poss["z"];
                    $homeLevel = $poss["level"];

                    if ($this->api->level->levelExists($homeLevel)) {
                        $pos = new Position($x, $y, $z, $this->api->level->get($homeLevel));
                        $issuer->teleport($pos);
                        $issuer->sendChat($this->config->get("home"));
                    } else {
                        $issuer->sendChat("The world you set your home in no longer exists.");
                    }
                } else {
                    $issuer->sendChat($this->config->get("no-home"));
                }
                break;
        }
    }

    public function __destruct() {
        $this->config->save();
    }
}