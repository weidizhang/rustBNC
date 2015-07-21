<?php
/*
 * rustBNC
 * @author Weidi Zhang <weidizhang0@gmail.com> (http://github.com/ebildude123)
 * @license CC BY-NC-SA 4.0 (SEE: LICENSE file)
 */

class rustBNCHandler extends rustBNCServices
{
	private $users;
	private $port;
	private $socket;
	
	protected $connections = array();	
	protected $ircHandler;
	
	private $onConnectRaw = array();
	private $chatLogBuffer = array();
	private $maxLogBuffer = 100; // debug
	
	protected $quitMsg = "rustBNC shutdown request";	
	protected $startTime;
	protected $serverAddr;
	
	public function __construct($config, $addr) {
		$this->users = $config["Users"];
		$this->port = $config["Port"];
		
		$this->serverAddr = $addr;		
		$this->startTime = time();
	}
	
	public function startServer() {
		$this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		socket_bind($this->socket, "0.0.0.0", $this->port);
		socket_listen($this->socket);
		socket_set_nonblock($this->socket);
	}
	
	public function acceptConnections() {
		$incomingUser = @socket_accept($this->socket);
		if ($incomingUser) {
			socket_set_nonblock($incomingUser);
			$this->connections[] = array(
				"Socket" => $incomingUser,
				"LoggedIn" => false,
				"LastPingSent" => 0,
				"LastPingRecv" => 0
			);
			
			$this->sendData($incomingUser, "NOTICE AUTH :*** You need to login, try: /quote PASS username:password");
		}
	}
	
	public function pingCheck() {
		foreach ($this->connections as $connID => $conn) {
			$pingWait = 300;
			if ((($conn["LastPingSent"] == 0) && ($conn["LastPingRecv"] == 0)) || (time() >= ($conn["LastPingSent"] + $pingWait))) {
				//echo "[sending ping check to]client #" . $connID . "\n"; // debug
				$conn["LastPingSent"] = time();
				$this->connections[$connID]["LastPingSent"] = time();
				$this->sendRaw($conn["Socket"], "PING :PHPRUSTBNCTIMEOUTCHECK");				
			}
			elseif ($conn["LastPingRecv"] >= ($conn["LastPingSent"] + $pingWait)) {
				socket_close($conn["Socket"]);
				unset($this->connections[$connID]);
			}
		}
	}
	
	public function afterLogin($user) {
		foreach ($this->ircHandler->GetChannels() as $chan) {
			foreach ($this->onConnectRaw as $connectData) {
				$this->sendRaw($user["Socket"], $connectData);
			}
				
			$this->sendRaw($user["Socket"], ":" . $this->ircHandler->GetNick() . "!*@* JOIN :" . $chan);
			$this->ircHandler->sendData("NAMES " . $chan);
			
			if (count($this->chatLogBuffer) > 0) {
				foreach ($this->chatLogBuffer as $channel => $data) {
					foreach ($data as $msg) {
						$this->sendRaw($user["Socket"], $msg);
					}
				}
				$this->chatLogBuffer = array();
			}
		}
	}
	
	public function listenOnce() {
		foreach ($this->connections as $connID => $conn) {			
			while (@$readRaw = trim($this->readData($conn["Socket"], 512))) {
				$readLines = explode("\n", $readRaw);
					
				foreach ($readLines as $readLine) {
					//echo "[sending to irc]" . $readLine . "\n"; // debug
						
					$readArgs = explode(" ", $readLine);
					$cmdType = @strtolower($readArgs[0]);
						
					if ($conn["LoggedIn"]) {
						if ($cmdType == "privmsg") {
							$msgData = rustIRCParser::onPrivMsg($readLine);
							if (strtolower($msgData->Channel) == "*rust") {
								$this->servicesProcess($conn, $msgData->Message);
							}
							else {
								foreach ($this->connections as $connID2 => $conn2) {
									if ($connID2 != $connID) {
										$this->sendRaw($conn2["Socket"], ":" . $this->ircHandler->getNick() . "!*@* PRIVMSG " . $msgData->Channel . " :" . $msgData->Message);
									}
								}
								$this->ircHandler->sendData($readLine);
							}
						}
						elseif ($cmdType != "quit") {
							$this->ircHandler->sendData($readLine);
						}
					}
					else {
						if ($cmdType == "pass") {							
							$loginDetails = $readArgs[1];
							$colonPosition = strpos($loginDetails, ":");
							
							if ($colonPosition !== false) {
								$loginUser = substr($loginDetails, 0, $colonPosition);
								$loginPass = substr($loginDetails, $colonPosition + 1);
								
								$loginSuccess = false;
								foreach ($this->users as $user) {
									if ((strtolower($user["Username"]) == strtolower($loginUser)) && (hash($user["HashMethod"], $loginPass) == $user["Password"])) {
										$conn["LoggedIn"] = true;
										$this->connections[$connID]["LoggedIn"] = true;
										$this->afterLogin($conn);
										$loginSuccess = true;
										break;
									}									
								}
								
								if (!$loginSuccess) {
									$this->sendData($conn["Socket"], "NOTICE AUTH :*** Invalid username or password.");
								}
							}
						}
					}
										
					if ($cmdType == "pong") {
						$pongMsg = $readArgs[1];
						if (substr($pongMsg, 0, 1) == ":") {
							$pongMsg = substr($pongMsg, 1);
						}
						
						if ($pongMsg == "PHPRUSTBNCTIMEOUTCHECK") {
							$conn["LastPingRecv"] = time();								
							$this->connections[$connID]["LastPingRecv"] = time();
							//echo "[recv pong check from]client #" . $connID . "\n"; // debug
						}
						else {
							$this->ircHandler->sendData($readLine);
						}
					}
					elseif ($cmdType == "quit") {
						socket_close($conn["Socket"]);
						unset($this->connections[$connID]);
					}		
				}
			}
		}
		
		$this->pingCheck();
	}
	
	public function sendClients($raw) {
		if (!empty($raw)) {
			foreach ($this->connections as $conn) {
				if ($conn["LoggedIn"]) {
					//echo "[sending to usr]" . $raw . "\n"; // debug
					$this->sendRaw($conn["Socket"], $raw);
				}
			}
		}
	}
	
	public function setIRCHandler($handle) {
		$this->ircHandler = $handle;
	}
	
	public function sendRaw($socket, $raw) {
		$raw .= "\n";
		socket_write($socket, $raw);
	}
	
	public function sendData($socket, $raw) {
		$serverName = "server.rustbnc.com";
		$buildStr = ":" . $serverName . " " . $raw;
		$this->sendRaw($socket, $buildStr);
	}
	
	public function readData($socket, $maxLen) {
		$sockRead = socket_read($socket, $maxLen);
		if (trim($sockRead) == null || !$sockRead) {
			return "";
		}
		$sockRead = str_replace("\r\n", "\n", $sockRead);
		$sockRead = str_replace("\r", "\n", $sockRead);
		$sockRead = preg_replace("/\n{2,}/", "\n", $sockRead);
		
		return $sockRead;
	}
	
	public function getSocketIP($socket) {
		$usrIP = "0.0.0.0";
		socket_getpeername($socket, $usrIP);
		return $usrIP;
	}
	
	public function addConnectRaw($raw) {
		$this->onConnectRaw[] = $raw;
	}
	
	public function connectRawCount() {
		return count($this->onConnectRaw);
	}	
	
	public function editNickConnectRaw($oldNick, $newNick) {		
		foreach ($this->onConnectRaw as $lineID => $rawData) {
			$rawDataArgs = explode(" ", $rawData);
			
			if ($lineID == 0) {
				$userInfo = explode("!", $rawDataArgs[count($rawDataArgs) - 1]);
				$userInfo[0] = $newNick;
				$rawDataArgs[count($rawDataArgs) - 1] = implode("!", $userInfo);
			}
			
			if (count($rawDataArgs) >= 3) {
				if ($rawDataArgs[2] == $oldNick) {
					$rawDataArgs[2] = $newNick;
				}
				
				if ($rawDataArgs[0] == (":" . $oldNick)) {
					$rawDataArgs[0] = ":" . $newNick;
				}
			}
			
			$this->onConnectRaw[$lineID] = implode(" ", $rawDataArgs);
		}
	}
	
	public function addChatLog($user, $chan, $msg) {
		if (count($this->connections) == 0) {			
			$timeFormat = "[" . date("h:m:s A") . "] ";
			if (substr($msg, 0, 8) == (chr(1) . "ACTION ")) {
				$msg = chr(1) . "ACTION " . $timeFormat . substr($msg, 8);
			}
			else {
				$msg = $timeFormat . $msg;
			}
			$msg = ":" . $user . " PRIVMSG " . $chan . " :" . $msg;
			
			if (!isset($this->chatLogBuffer[$chan])) {
				$this->chatLogBuffer[$chan] = array();
			}
			
			$this->chatLogBuffer[$chan][] = $msg;
			if (count($this->chatLogBuffer[$chan]) > $this->maxLogBuffer) {
				array_shift($this->chatLogBuffer[$chan]);
			}
		}
	}
	
	public function shutdown() {
		socket_close($this->socket);
	}
}
?>