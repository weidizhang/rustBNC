<?php
/*
 * rustBNC
 * @author Weidi Zhang <weidizhang0@gmail.com> (http://github.com/ebildude123)
 * @license CC BY-NC-SA 4.0 (SEE: LICENSE file)
 */
 
class rustIRCParser
{
	/* From Server */
	public static function onNickChange($raw) {
		$changed = array();
		
		$data = explode(" ", $raw);
		$userInfo = explode("!", $data[0]);
		$changed["OldNick"] = trim(substr($userInfo[0], 1));
		$changed["NewNick"] = trim(substr($data[2], 1));
		
		return (object) $changed;
	}
	
	public static function onJoinPart($raw) {
		$output = array();
		
		$data = explode(" ", $raw);
		$nick = explode("!", substr($data[0], 1));
		$nick = $nick[0];
		$channel = $data[2];
		if (substr($channel, 0, 1) == ":") {
			$channel = substr($channel, 1);
		}
		$output["Nick"] = $nick;
		$output["Channel"] = strtolower($channel);
		
		return (object) $output;
	}
	
	public static function onKick($raw) {
		$output = array();
		
		$data = explode(" ", $raw);
		$output["Channel"] = strtolower($data[2]);
		$output["Nick"] = $data[3];
		
		return (object) $output;
	}
	
	public static function onPrivMsgServer($raw) {
		$output = array();
		
		$data = explode(" ", $raw);
		$output["User"] = substr($data[0], 1);
		$output["Channel"] = strtolower($data[2]);
		$output["Message"] = implode(" ", array_slice($data, 3));
		if (substr($output["Message"], 0, 1) == ":") {
			$output["Message"] = substr($output["Message"], 1);
		}
		
		return (object) $output;
	}
	
	/* From Client */
	public static function onPrivMsg($raw) {
		$output = array();
		
		$data = explode(" ", $raw);
		$output["Channel"] = strtolower($data[1]);
		$output["Message"] = implode(" ", array_slice($data, 2));
		if (substr($output["Message"], 0, 1) == ":") {
			$output["Message"] = substr($output["Message"], 1);
		}
		
		return (object) $output;
	}
}
?>