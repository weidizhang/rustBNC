<?php
/*
 * rustBNC
 * @author Weidi Zhang <weidizhang0@gmail.com> (http://github.com/ebildude123)
 * @license CC BY-NC-SA 4.0 (SEE: LICENSE file)
 */
 
class rustIRCHandler
{
	private $ircServer;
	private $ircPort;
	private $ircPassword;
	private $ircSSL;
	
	private $ircNick;
	private $ircNickPass;
	private $ircIdent;
	private $ircRealName;
	
	private $ircChan;
	
	private $socket;
	
	private $bncHandler;
	private $connecting = false;
	
	public function __construct($config) {
		$this->ircServer = $config["Address"];
		$this->ircPort = $config["Port"];
		$this->ircPassword = $config["Password"];
		$this->useSSL = $config["UseSSL"];
		
		$this->ircNick = $config["Nickname"];
		$this->ircNickPass = $config["NickPassword"];
		$this->ircIdent = $config["Identity"];
		$this->ircRealName = $config["RealName"];
		
		$this->ircChan = array_map("strtolower", $config["Channels"]);
	}
	
	public function GetChannels() {
		return $this->ircChan;
	}
	
	public function GetNick() {
		return $this->ircNick;
	}
	
	public function setBNCHandler($handle) {
		$this->bncHandler = $handle;
	}
	
	public function connectToServer()
    {
		$serverStr = $this->ircServer;
		if ($this->ircSSL) {
			$serverStr = "ssl://" . $serverStr;
		}
        $this->socket = fsockopen($serverStr, $this->ircPort, $errno, $errstr, 5);
		stream_set_blocking($this->socket, false);
        if ($this->socket) {
			if (!empty($this->ircServerPass)) {
				$this->sendData("PASS " . $this->ircPassword);
			}
            $this->sendData("NICK " . $this->ircNick);
            $this->sendData("USER " . $this->ircIdent . " 0 * :" . $this->ircRealName);
        }
		else {
			die("Error connecting to IRC server: " . $errstr . " (Error #" . $errno . ")");
		}
    }
	
	public function shutdown() {
		fclose($this->socket);
	}
	
	public function listenOnce() {
		$rawRead = fgets($this->socket, 512);
		if (!empty($rawRead)) {
			$readArgs = explode(" ", $rawRead);
			$cmdType = @$readArgs[1];

			if (substr($rawRead, 0, 6) == "PING :") {
				$this->sendData("PONG :" . substr($rawRead, 6));
			}
			elseif ($cmdType == "001") {
				$this->connecting = true;
				$this->sendData("PRIVMSG NickServ :IDENTIFY " . $this->ircNickPass);
				$this->channelJoin(implode(",", $this->ircChan));
				$this->ircChan = array();
			}
			elseif ($cmdType == "433") {
				if ($this->bncHandler->connectRawCount() == 0) {
					if (strlen($this->ircNick) < 5) {
						$maxLen = strlen($this->ircNick);
					}
					else {
						$maxLen = 5;
					}
					$generatedNick = substr($this->ircNick, 0, $maxLen) . rand(10000, 99999);
					$this->changeNick($generatedNick);
					$this->ircNick = $generatedNick;
				}
			}
			elseif ($cmdType == "NICK") {
				$data = rustIRCParser::onNickChange($rawRead);
				if ($data->OldNick == $this->ircNick) {
					$this->ircNick = $data->NewNick;
					$this->bncHandler->editNickConnectRaw($data->OldNick, $data->NewNick);
				}
			}
			elseif ($cmdType == "JOIN") {
				$data = rustIRCParser::onJoinPart($rawRead);
				if (strtolower($data->Nick) == strtolower($this->ircNick)) {	
					if (!in_array($data->Channel, $this->ircChan)) {
						$this->ircChan[] = $data->Channel;
					}
				}
			}
			elseif ($cmdType == "PART") {
				$data = rustIRCParser::onJoinPart($rawRead);
				if (strtolower($data->Nick) == strtolower($this->ircNick)) {
					$this->removeChannel($data->Channel);
				}
			}
			elseif ($cmdType == "KICK") {
				$data = rustIRCParser::onKick($rawRead);
				if (strtolower($data->Nick) == strtolower($this->ircNick)) {
					$this->removeChannel($data->Channel);
				}
			}
			elseif ($cmdType == "PRIVMSG") {
				$data = rustIRCParser::onPrivMsgServer($rawRead);
				$this->bncHandler->addChatLog($data->User, $data->Channel, $data->Message);
			}
			
			if ($this->connecting) {
				$this->bncHandler->addConnectRaw($rawRead);
			}
			
			if ($cmdType == "MODE") {
				$this->connecting = false;
			}
			
			//echo $rawRead . "\n"; // debug
			$this->bncHandler->sendClients($rawRead);
		}
	}
	
	public function removeChannel($chan) {
		$chanKey = array_search($chan, $this->ircChan);
		if ($chanKey !== false) {
			unset($this->ircChan[$chanKey]);
		}
	}
	
	public function sendData($str)
	{
		$str = $str . "\n";
		fwrite($this->socket, $str, strlen($str));
	}
	
	public function channelJoin($chan)
	{
		$this->sendData("JOIN " . $chan);
	}
	
	public function changeNick($nick) {
		$this->sendData("NICK " . $nick);
	}
}
?>