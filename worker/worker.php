#!/usr/bin/php
<?php
$hostname = php_uname('n');
// @TODO: Get the server key rom a config file, or pass it on, or get it from an environment variable
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
		  $hn = trim(`hostname -s`);
		  $r = explode(" ", `uptime`);
		  $loadavg = $r[count($r) - 3];

		  echo "Load\n";
		  $load = round(($loadavg * 10));
		  if ($load > 100) {
					 $load = 102; // 102 means no more traffic
		  }

		  $ch = curl_init();

		  curl_setopt($ch, CURLOPT_URL, "http://msiof.smellynose.com/server");
		  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		  curl_setopt($ch, CURLOPT_POST, true);
		  curl_setopt($ch, CURLOPT_HTTPHEADER, array('X-Server-Key: ' . $server['serverKey']));


		  $server['name'] = $hn;
		  $server['load'] = $load;
		  $server['loadavg'] = preg_replace('/[^0-9\.]/', '', $loadavg);
		  echo "Before conns\n";
		  $server['entropy'] = trim(`cat /proc/sys/kernel/random/entropy_avail`);
		  $server['conns'] = file('http://localhost/action/admin_server_status?auto');
		  $exploded = explode(':', $server['conns'][3]);
		  $end = end($exploded);

		  $server['conns'] = trim($end);
		  echo "After conns\n";

		  $server['maindiskusage'] = trim(`df -h | grep "% /"$ | awk '{print $5}'`);
		  echo "Disk\n";


		  $server['time'] = `date +"%H:%M:%S"`;
		  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postFields));


		  echo "Loop 2\n";

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
