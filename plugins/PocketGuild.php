<?php

/*
 __PocketMine Plugin__
name=PocketGuild
description=You can ally yourself with other players.
version=1.0
author=MinecrafterJPN
class=PocketGuild
apiversion=10
*/

class PocketGuild implements Plugin
{
	private $api, $guild, $user;

	public function __construct(ServerAPI $api, $server = false)
	{
		$this->api = $api;
	}

	public function init()
	{
		$this->guild = new Config($this->api->plugin->configPath($this) . "guild.yml", CONFIG_YAML);
		$this->user = new Config($this->api->plugin->configPath($this) . "user.yml", CONFIG_YAML);
		$this->api->console->register("guild", "PocketGuild command", array($this, "commandHandler"));
		$this->api->console->alias("g", "guild");
	}

	public function commandHandler($cmd, $args, $issuer, $alias)
	{
		if ($issuer === "console") {
			console("[PocketGuild] Must be run on the world.");
			return;
		}
		$cmd = strtolower($cmd);
		$output = "";
		switch ($cmd) {
			case "guild":
				$subCommand = strtolower($args[0]);
				switch ($subCommand) {
					case "":
						if ($this->user->exists($issuer->username)) {
							$guildname = $this->user->get($issuer->username);
							$output .= "[PocketGuild] Your guild : $guildname";
						} else {
							$output .= "[PocketGuild] You are not part of guild.";
						}
						break;
					case "organize":
					case "org":
						if ($this->user->exists($issuer->username)) {
							$output .= "[PocketGuild] You have already joined a guild.";
							break;
						}
						$guildname = $args[1];
						if ($guildname === "") {
							$output .= "[PocketGuild] Invalid name";
							break;
						}
						if ($this->guild->exists($guildname)) {
							$output .= "[PocketGuild] The name already exists.";
						} else {
							$this->guild->set($guildname, array("members" => array($issuer->username)));
							$this->guild->save();
							$this->user->set($issuer->username, $guildname);
							$this->user->save();
							$output .= "[PocketGuild] Completed organizing!";
						}
						break;
					case "join":
						if ($this->user->exists($issuer->username)) {
							$output .= "[PocketGuild] You have already joined a guild.";
							break;
						}
						$guildname = $args[1];
						if ($this->guild->exists($guildname)) {
							$guildinfo = $this->guild->get($guildname);
							array_push($guildinfo["members"], $issuer->username);
							$this->guild->set($guildname, $guildinfo);
							$this->guild->save();
							$this->user->set($issuer->username, $guildname);
							$this->user->save();
							$output .= "[PocketGuild] Completed joining!";
						} else {
							$output .= "[PocketGuild] The guild dose not exist.";
						}
						break;
					case "leave":
						if (!$this->user->exists($issuer->username)) {
							$output .= "[PocketGuild] You are not part of guild.";
							break;
						}
						$guildname = $this->user->get($issuer->username);
						$guildinfo = $this->guild->get($guildname);
						$index = array_search($issuer->username, $guildinfo["members"]);
						unset($guildinfo["members"][$index]);
						$this->guild->set($guildname, $guildinfo);
						$this->guild->save();
						$this->user->remove($issuer->username);
						$this->user->save();
						$output .= "[PocketGuild] Completed leaving!";
						break;
					case "chat":
					case "c":
						$guildname = $this->user->get($issuer->username);
						$msg = "[" . $guildname . "]" . "<" . $issuer->username . ">" . $args[1];
						$guildinfo = $this->guild->get($guildname);
						$this->api->chat->send(false, $msg, $guildinfo["members"]);
						console("[INFO]". $msg);
						break;
					default:
						$output .= "[PocketGuild] /guild $subCommand dose not exist.";
						break;
				}
				break;
		}
		return $output;
	}

	public function __destruct()
	{
		$this->guild->save();
		$this->user->save();
	}
}
