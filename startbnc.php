<?php
/*
 * rustBNC
 * @author Weidi Zhang <weidizhang0@gmail.com> (http://github.com/ebildude123)
 * @license CC BY-NC-SA 4.0 (SEE: LICENSE file)
 */
 
set_time_limit(0);
require "class.ircparser.php";
require "class.services.php";
require "class.irc.php";
require "class.bnc.php";
require "class.rust.php";

$rustBNC = new rustBNC("rust.json");
$rustBNC->startServer();

echo
"=========================================
                 _   ____  _   _  _____ 
                | | |  _ \| \ | |/ ____|
  _ __ _   _ ___| |_| |_) |  \| | |     
 | '__| | | / __| __|  _ <| . ` | |     
 | |  | |_| \__ \ |_| |_) | |\  | |____ 
 |_|   \__,_|___/\__|____/|_| \_|\_____|
=========================================
* rustBNC coded by Weidi Zhang          *
* rustBNC is now starting...            *
* Unix users: run in \"screen\"           *
* Windows users: leave cmd window open  *
=========================================
";

while (true) {
	$rustBNC->run();
}
?>
