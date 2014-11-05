#!/usr/bin/php
<?php
$hostname = php_uname('n');
// @TODO: Get the server key from a config file, or pass it on, or get it from an environment variable
$serverKey = sha1($hostname.'CHEESE_THIS_IS_JUST_A_TEST_SO_THIS_DOESNT_MATTER');
$loopLength = 60;

$lockfile = '/tmp/serverupdatelock';
$pid = getmypid();
echo "My PID is {$pid}\n";

if (file_exists($lockfile) && (time() - filemtime($lockfile)) < 300) {
		  exit('Somebody got a lock, me no run. Its this old in seconds:' . (time() - filemtime($lockfile)));
}

touch($lockfile);

$run = true;
$loopStartTime = time();
$server = Array();
$server['serverKey'] = $serverKey;

while ($run) {
		  $loopStartTime = time();
		  file_put_contents($lockfile, $pid);
		  echo "Loop\n";

		  $hn = php_uname('n');
		  $loadavg = sys_getloadavg();
		  $loadavg = $loadavg[0];

		  $ch = curl_init();

		  curl_setopt($ch, CURLOPT_URL, "http://msiof.smellynose.com/server");
		  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		  curl_setopt($ch, CURLOPT_POST, true);
		  curl_setopt($ch, CURLOPT_HTTPHEADER, array('X-Server-Key: ' . $server['serverKey']));


		  $server['name'] = $hn;
		  $server['loadavg'] = $loadavg;
		  $server['entropy'] = trim(file_get_contents('/proc/sys/kernel/random/entropy_avail'));
		  $server['conns'] = getConnectionsByPort();
		  $server['maindiskusage'] = trim(`df -h | grep "% /"$ | awk '{print $5}'`);
		  $server['time'] = date('H:i:s');

		  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($server));

		  $r = curl_exec($ch);

		  if ($r === false) {
					 echo("FAILED");
		  } else {
					 echo "\n\n\n\n".$r."\n\n\n\n";
		  }

		  $loopEndTime = time();
		  $loopTimeTook = $loopEndTime - $loopStartTime;
		  if ($loopTimeTook < $loopLength) {
					 echo "Sleeping so we hit every 60 seconds\n";
					 sleep($loopLength - $loopTimeTook);
		  }
}
