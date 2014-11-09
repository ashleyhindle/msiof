#!/usr/bin/php
<?php
$configFile = '/etc/msiof/msiof.conf';
if (!file_exists($configFile)) {
		  echo "Config file doesn't exist: {$configFile}\n";
		  exit(1);
}

$config = parse_ini_file($configFile);
if (empty($config)) {
		  echo "Config file can't be read, or is empty or invalid: {$configFile}\n";
		  exit(1);
}

if (empty($config['key'])) {
		  echo "key isn't set in config file: {$configFile}\n";
		  exit(1);
}

$hostname = php_uname('n');
// @TODO: Get the server key from a config file, or pass it on, or get it from an environment variable
$serverKey = trim($config['key']);
echo "Key from configFile is {$serverKey}\n";
$loopLength = 60;

$run = true;
$loopStartTime = time();
$server = Array();
$server['serverKey'] = $serverKey;

while ($run) {
		  $loopStartTime = time();

		  $hostname = php_uname('n');

		  $ch = curl_init();
		  curl_setopt($ch, CURLOPT_URL, "http://msiof.smellynose.com/server");
		  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		  curl_setopt($ch, CURLOPT_POST, true);
		  curl_setopt($ch, CURLOPT_HTTPHEADER, array('X-Server-Key: ' . $server['serverKey']));

		  $server['workerversion'] = '0.01';
		  $server['name'] = $hostname;
		  $server['entropy'] = trim(file_get_contents('/proc/sys/kernel/random/entropy_avail'));
		  $server['conns'] = getConnectionsByPort();
		  $server['cpu'] = getCpuInfo();
		  $server['mem'] = getMemInfo();
		  $server['disk'] = getDiskInfo();
		  $server['network'] = getNetworkInfo();
		  $server['system'] = getSystemInfo();
		  $server['time'] = date('Y-m-d H:i:s');

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

/**
 * Only supports TCP for now
 * TODO: Support UDP and IPV6
 *
 * @return array
 **/
function getConnectionsByPort()
{
		  $lines = file('/proc/net/tcp');
		  array_shift($lines);
		  $connections = array();
		  foreach ($lines as $l) {
					 $exploded = preg_split('/\s+/', trim($l));

					 $localExploded = explode(':', $exploded[1]);
					 $localAddr = long2ip(hexdec(implode('', array_reverse(str_split($localExploded[0], 2)))));
					 $localPort = hexdec($localExploded[1]);

					 $remoteExploded = explode(':', $exploded[2]);
					 $remoteAddr = long2ip(hexdec(implode('', array_reverse(str_split($remoteExploded[0], 2)))));
					 $remotePort = hexdec($remoteExploded[1]);

					 $socketStatus = $exploded[3];

					 if ($socketStatus != '01') {
								continue;
					 }


					 if (!array_key_exists($localPort, $connections)) {
								$connections[$localPort] = 0;
					 }
					 $connections[$localPort]++;
		  }

		  return $connections;
}

/**
 * getCpuInfo
 *
 * @return array
 */
function getCpuInfo()
{
		  $lines = file('/proc/stat');
		  array_shift($lines); // Don't use combined CPU info
		  $cpus = array();

		  foreach ($lines as $l) {
					 if (strpos($l, 'cpu') === false) {
								continue;
					 }

					 $cpu = preg_split('/\s+/', trim($l));
					 $cpus[] = array(
								'user' => $cpu[1],
								'nice' => $cpu[2],
								'system' => $cpu[3],
								'idle' => $cpu[4],
								'wait' => $cpu[5],
								'iowait' => $cpu[6],
								'irq' => $cpu[7],
								'softirq' => $cpu[8],
								'steal' => $cpu[9],
								'guest' => $cpu[10],
					 );
		  }

		  return $cpus;
}

/**
 * getMemInfo
 *
 * @return array
 */
function getMemInfo()
{
		  $lines = file_get_contents('/proc/meminfo');
		  preg_match_all('/([a-zA-Z0-9]+):\s+([0-9]+) kB/', $lines, $matches);
		  array_walk(
					 $matches[1],
					 function(&$value) {
								$value = strtolower($value);
					 }
		  );
		  $mem = array_combine($matches[1], $matches[2]);

		  return $mem;
}

/**
 * getSystemInfo
 *
 * @return array
 */
function getSystemInfo()
{
		  $uname = php_uname();
		  list($sysname, $hostname, $release, $version, $machine) = explode(' ', $uname);

		  $system = array();
		  $system['cpu'] = array(
					 'cores' => count(preg_grep('/^(processor)/', file('/proc/cpuinfo'))),
					 'type' => trim(end(explode(':', trim(current(preg_grep('/model name/', file('/proc/cpuinfo')))))))
		  );

		  $system['loadavg'] = current(explode(' ', trim(file_get_contents('/proc/loadavg'))));
		  $system['sysname'] = $sysname;
		  $system['hostname'] = $hostname;
		  $system['release'] = $release;
		  $system['version'] = $version;
		  $system['machine'] = $machine;
		  $system['uptime'] = current(explode(' ', current(file('/proc/uptime'))));

		  return $system;
}

/**
 * getDiskInfo
 *
 * @return array
 */
function getDiskInfo()
{
		  $disk = array();
		  $disk['/'] = array(
					 'total' => disk_total_space('/'),
					 'free' => disk_free_space('/')
		  );

		  return $disk;
}

/**
 * getNetworkInfo
 *
 * @return array
 */
function getNetworkInfo()
{
		  $network = array();
		  $lines = file('/proc/net/dev');
		  array_shift($lines);
		  array_shift($lines);

		  foreach ($lines as $l) {
					 $exploded = preg_split('/\s+/', preg_replace('/\s+/', ' ', trim($l)));
					 $interface = substr($exploded[0], 0, -1);
					 $rxBytes = $exploded[1];
					 $txBytes = $exploded[9];
					 //Not received or sent any data, don't bother with it
					 if (empty($rxBytes) && empty($txBytes)) {
								continue;
					 }

					 $network[$interface] = array(
								'rxbytes' => $rxBytes,
								'txbytes' => $txBytes
					 );
		  }

		  return $network;
}
