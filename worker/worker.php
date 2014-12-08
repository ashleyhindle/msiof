#!/usr/bin/php
<?php
function isEnabled($func) {
    return is_callable($func) && false === stripos(ini_get('disable_functions'), $func);
}

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
$loopLength = 30;

$run = true;
$loopStartTime = time();
$server = Array();
$server['serverKey'] = $serverKey;

while ($run) {
    $loopStartTime = time();

    $hostname = php_uname('n');

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, (empty($config['api_url'])) ? "http://msiof.smellynose.com/server" : $config['api_url']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('X-Server-Key: ' . $server['serverKey']));

    $server['workerversion'] = 1.3;
    $server['name'] = $hostname;
    $server['entropy'] = trim(file_get_contents('/proc/sys/kernel/random/entropy_avail'));
    $server['conns'] = getConnectionsByPort();
    $server['cpu'] = getCpuInfo();
    $server['mem'] = getMemInfo();
    $server['process'] = getProcessInfo();
    $server['disk'] = getDiskInfo();
    if(isEnabled('shell_exec')) {
        $server['disk'] = getDiskInfoSysCall();
    }
    $server['network'] = getNetworkInfo();
    $server['system'] = getSystemInfo();
    $server['time'] = date('Y-m-d H:i:s');
    $server['microtime'] = microtime(true);

    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($server));

    $r = curl_exec($ch);

    if ($r === false) {
        echo("FAILED");
    } else {
        echo $r."\n";
    }

    $loopEndTime = time();
    $loopTimeTook = $loopEndTime - $loopStartTime;
    if ($loopTimeTook < $loopLength) {
        echo "Sleeping so we hit every 60 seconds\n";
        $sleepLength = $loopLength - $loopTimeTook;
        $sleepLength = ($sleepLength < 0) ? 0 : $sleepLength;
        sleep($sleepLength);
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
    $cpus = array();

    foreach ($lines as $l) {
        if (strpos($l, 'cpu') === false) {
        continue;
        }

        $cpu = preg_split('/\s+/', trim($l));
        $cpus[$cpu[0]] = array(
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

function arrayWalkStrToLower(&$value) {
    $value = strtolower($value);
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
        'arrayWalkStrToLower'
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
    $rootTotal = disk_total_space('/');
    $rootFree = disk_free_space('/');
    $rootUsed = $rootTotal - $rootFree;
    $disk['/'] = array(
    'total' => $rootTotal,
    'free' => $rootFree,
    'used' => $rootUsed
    );

    return $disk;
}

function getDiskInfoSysCall()
{
    $disks = array();
    $command = "df -l --output='source,fstype,itotal,iused,iavail,ipcent,size,used,avail,pcent,target'";
    $regex = "/([\w\/]+)\s+(\w+)\s+([0-9]+)\s+([0-9]+)\s+([0-9]+)\s+([0-9%]+)\s+([0-9]+)\s+([0-9]+)\s+([0-9]+)\s+([0-9%]+)\s+(.*)/";

    $command = "df -lTP";
    $regex = "/([\w\/]+)\s+(\w+)\s+([0-9]+)\s+([0-9]+)\s+([0-9]+)\s+([0-9%]+)\s+(.*)/";

    $result = preg_match_all($regex, shell_exec($command), $matches);

    array_shift($matches);
    $disks = array();
    for ($i=0; $i < count($matches[0]); $i++) {
        list(
        $filesystem,
        $type,
        $size,
        $used,
        $avail,
        $pcent,
        $target) = array(
        $matches[0][$i],
        $matches[1][$i],
        $matches[2][$i],
        $matches[3][$i],
        $matches[4][$i],
        $matches[5][$i],
        $matches[6][$i],
        );

        $disks[$target] = array(
            'filesystem' => $filesystem,
            'type' => $type,
            'total' => $size,
            'used' => $used,
            'free' => $avail,
            'pcent' => str_replace('%', '', $pcent),
            'target' => $target
        );
    }

    return $disks;
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
        $exploded = explode(':', preg_replace('/\s+/', ' ', trim($l)), 2);
        $interface = trim($exploded[0]);

        $exploded = preg_split('/\s+/', trim($exploded[1]));
        $rxBytes = $exploded[0];
        $txBytes = $exploded[8];
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

/**
* getProcessInfo
*
* @return array
*/
function getProcessInfo()
{
    $dir = new DirectoryIterator('/proc/');
    $dir = new RegexIterator($dir, '/^([0-9]*)$/');
    $processes = array();

    foreach ($dir as $process) {
        $processId = $process->getFilename();
        $statusFile = file($process->getPathname() . '/status', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $statFile = file_get_contents($process->getPathname() . '/stat');
        $cmdlineFile = file_get_contents($process->getPathname() . '/cmdline');
        $ioFile = false;
        $io = array(
            'read_bytes' => 0,
            'write_bytes' => 0
        );

        if (file_exists($process->getPathname() . '/io')) {
            $ioFile = file($process->getPathname() . '/io', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            foreach ($ioFile as $line) {
                list($key, $value) = explode(':', $line);
                $key = strtolower(trim($key)); //Standardise key
                $value = trim($value);
                $io[$key] = $value;
            }
        }

        if (empty($statusFile) || empty($statFile) || empty($cmdlineFile)) {
            // Lost process during info retrieval
            continue;
        }

        $command = explode("\0", $cmdlineFile, 2);
        $program = $command[0];
        $args = explode("\0", $command[1]);

        $status = array();
        
        foreach ($statusFile as $line) {
            list($key, $value) = explode(':', $line);
            $key = strtolower(trim($key)); //Standardise key
            $value = trim($value);
            $status[$key] = $value;
        }

		  if (function_exists('posix_getpwuid')) {
		      $user = posix_getpwuid($status['uid']);
            $user = $user['name'];
		  } else {
		      $user = $status['uid'];
		  }

        $stat = preg_split('/\s+/', trim($statFile));

        $processes[$processId] = array(
            'program' => $program,
            'user' => $user,
            'processid' => $processId,
            'mem' => trim(preg_replace('/[^0-9]/', '', $status['vmrss'])) * 1024, // Store memory in bytes
            'cpu' => array(
                'user' => $stat[13], //includes guest time http://man7.org/linux/man-pages/man5/proc.5.html
                'system' => $stat[14],
                'total' => $stat[13] + $stat[14],
                'starttime' => $stat[21]
                        /*
                        In kernels before Linux 2.6, this value was expressed
                        in jiffies.  Since Linux 2.6, the value is expressed
                        in clock ticks (divide by sysconf(_SC_CLK_TCK)).
                        */
            ),
            'io' => $io
        );
    }

    $overview = array();

    foreach ($processes as $process) {
        $exploded = explode(' ', $process['program']);
        $process['program'] = trim($exploded[0], ':');
        if (!array_key_exists($process['program'], $overview)) {
            $overview[$process['program']] = array(
                'count' => 0,
                'mem' => 0,
                'cpu' => 0,
                'readbytes' => 0,
                'writebytes' => 0
                );
        }

        $overview[$process['program']]['count']++;
        $overview[$process['program']]['mem'] += $process['mem']; //bytes
        $overview[$process['program']]['cpu'] += $process['cpu']['total'];
        $overview[$process['program']]['readbytes'] += $process['io']['read_bytes'];
        $overview[$process['program']]['writebytes'] += $process['io']['write_bytes'];
    }

    return $overview;
}
