<?php
/*
 * rustBNC
 * @author Weidi Zhang <weidizhang0@gmail.com> (http://github.com/ebildude123)
 * @license CC BY-NC-SA 4.0 (SEE: LICENSE file)
 */
 
class rustBNCServices
{
	public function servicesProcess($conn, $msg) {
		$newMsg = trim(strtolower($msg));
		if ($newMsg == "help") {
			$this->cmdSendHelp($conn);
		}
		elseif ($newMsg == "clients") {
			$this->cmdSendClients($conn);
		}
		elseif ($newMsg == "serverinfo") {
			$this->cmdSendServerInfo($conn);
		}
		elseif ($newMsg ==  "shutdown") {
			$this->cmdSendShutdown($conn);
		}
		elseif ($newMsg == "about") {
			$this->cmdSendAbout($conn);
		}
		else {
			$this->cmdSendUnknownCmd($conn);
		}
	}
	
	private function serviceSend($conn, $msg) {
		$this->sendRaw($conn["Socket"], ":*rust!services@rustbnc.com PRIVMSG " . $this->ircHandler->getNick() . " :" . $msg);
	}
	
	public function cmdSendHelp($conn) {
		$commands = array(
			"HELP" => "Displays all available commands",
			"CLIENTS" => "Displays all connected clients",
			"SERVERINFO" => "Displays server information",
			"SHUTDOWN" => "Shutsdown the BNC server gracefully",
			"ABOUT" => "Displays information about the rustBNC project"
		);
		$longestLength = max(array_map("strlen", array_flip($commands)));
		
		foreach ($commands as $command => $description) {
			$cmdFormatted = $command . str_repeat(" ", $longestLength - strlen($command));
			$this->serviceSend($conn, "- " . $cmdFormatted . "  " . $description);
		}
	}
	
	public function cmdSendClients($conn) {	
		$output = array();
		foreach ($this->connections as $conn2) {
			$ip = $this->getSocketIP($conn2["Socket"]);
			if (!isset($output[$ip])) {
				$output[$ip] = 0;
			}
			$output[$ip]++;
		}
		
		foreach ($output as $ip => $uniqueConns) {
			$multipleConn = "";
			if ($uniqueConns > 1) {
				$multipleConn = "s";
			}
			$this->serviceSend($conn, "- " . $ip . " has " . $uniqueConns . " active connection" . $multipleConn);
		}
	}
	
	public function cmdSendServerInfo($conn) {
		$this->serviceSend($conn, "- Connected to: " . $this->serverAddr);
		$this->serviceSend($conn, "- BNC server uptime: " . $this->secondsToTime(time() - $this->startTime));
	}
	
	public function cmdSendShutdown($conn) {
		foreach ($this->connections as $conn2) {
			$this->serviceSend($conn2, "- BNC server shutdown request received from " . $this->getSocketIP($conn["Socket"]));
			socket_close($conn2["Socket"]);			
		}		
		
		
		$this->shutdown();
		sleep(5); // debug - do we need this
		$this->ircHandler->sendData("QUIT :" . $this->quitMsg);
		$this->ircHandler->shutdown();
		die();
	}
	
	public function cmdSendAbout($conn) {
		$this->serviceSend($conn, "- rustBNC is a free IRC bouncer coded in PHP by Weidi Zhang");
	}
	
	public function cmdSendUnknownCmd($conn) {
		$this->serviceSend($conn, "- Unknown command. Please reply with \"HELP\" to list all commands");
	}

	private function secondsToTime($seconds) {
		$dtF = new DateTime("@0");
		$dtT = new DateTime("@$seconds");
		return $dtF->diff($dtT)->format('%a days, %h hours, %i minutes, %s seconds');
	}
}
?>
