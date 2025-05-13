<?php
/*
name=Trampolines
version=1
author=you
class=Trampolines
apiversion=12, 12.1
*/
class Trampolines implements Plugin{
    private $api, $cfg;
    public function __construct(ServerAPI $api, $server = false){
        $this->api=$api;
    }
    public function init(){
        $this->cfg=new Config($this->api->plugin->configPath($this)."config.yamal", CONFIG_YAML, [
            "trampoline-block" => "171:8", //gray carpet
            "jump-boost" => 10
        ]);
        $this->api->addHandler("player.move", [$this, "trampolines"]);
    }
    public function trampolines($d, $e){
        $inw=$d->level->getBlock(new Vector3($d->x, $d->y, $d->z));
        $expec=$this->api->block->fromString($this->cfg->get("trampoline-block"));
        if($inw->getID()==$expec->getID() && $inw->getMetadata()==$expec->getMetadata()){
            $pk=new SetEntityMotionPacket;
            $pk->eid=0;
            $pk->speedX=0;
            $pk->speedY=$this->cfg->get("jump-boost");
            $pk->speedZ=0;
            $this->api->player->getByEID($d->eid)->dataPacket($pk);
        }
    }
}