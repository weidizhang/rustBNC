<?php
/*
 * rustBNC
 * @author Weidi Zhang <weidizhang0@gmail.com> (http://github.com/ebildude123)
 * @license CC BY-NC-SA 4.0 (SEE: LICENSE file)
 */
 
class rustBNC
{
	private $ircHandler;
	private $bncHandler;
	
	public function __construct($configFile) {
		if (file_exists($configFile)) {
			$configJSON = json_decode(file_get_contents($configFile), true);
			
			$this->ircHandler = new rustIRCHandler($configJSON["IRC"]);
			$this->bncHandler = new rustBNCHandler($configJSON["BNC"], $configJSON["IRC"]["Address"]);
			
			$this->bncHandler->setIRCHandler($this->ircHandler);
			$this->ircHandler->setBNCHandler($this->bncHandler);
		}
		else {
			die("Specified configuration file not found.");
		}
	}
	
	public function startServer() {
		$this->ircHandler->connectToServer();
		$this->bncHandler->startServer();
	}
	
	public function run() {
		$this->ircHandler->listenOnce();
		$this->bncHandler->acceptConnections();
		$this->bncHandler->listenOnce();
	}
}
?>