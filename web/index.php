<?php
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

require_once __DIR__.'/../vendor/autoload.php';

$userId = 100;

$app = new Application();
// Please set to false in a production environment
//$app['debug'] = true;
$app->register(new Predis\Silex\ClientServiceProvider(), [
		  'predis.parameters' => 'tcp://127.0.0.1:6379',
		  'predis.options'    => [
					 'prefix'  => 'msiof:',
					 'profile' => '3.0',
		  ],
]);

//$nextUserId = $app['predis']->incr('next_user_id');

$app->get('/', function(Application $app, Request $request) {
		  echo "curl -s http://msiof.smellynose.com/install | bash<hr>";
		  $serverKeys = $app['predis']->lrange('user:100:servers', 0, -1);
		  echo ' <meta http-equiv="refresh" content="30"><pre>';
		  foreach ($serverKeys as $serverKey) {
					 $server = json_decode($app['predis']->get("server:{$serverKey}"), true);
					 $msiofTime = date('Y-m-d H:i:s', $server['lastupdated']);
					 echo "{$server['name']}<br>Load: {$server['system']['loadavg']} - Connections on port 80: {$server['conns'][80]} - Server Time: {$server['time']} - MSIOF Time: {$msiofTime}<hr>";
		  }
		  echo '</pre>';

		  return '';
});

$app->get('/install', function(Application $app) {
		  return <<<BASH
#!/bin/bash
if [[ \$EUID -ne 0 ]]; then
   echo "This script must be run as root" 1>&2
   exit 1
fi

echo "=== Creating /etc/msiof/"
mkdir /etc/msiof/

echo "=== Installing php-cli"
if [ -e "/etc/redhat-release" ]; then
		  yum install -y php-cli
fi

if [ -e "/etc/debian_version" ]; then
		  apt-get -y install php5-cli
fi

if [ ! -f /etc/msiof/msiof.conf ]; then
		  echo "=== Config file doesn't exist, creating /etc/msiof/msiof.conf..."
		  curl -o /etc/msiof/msiof.conf http://msiof.smellynose.com/key
fi

echo "=== Downloading worker to /etc/msiof/worker..."
curl -s -o /etc/msiof/worker http://msiof.smellynose.com/worker-php
chmod a+x /etc/msiof/worker

echo "=== Installing init"
### INIT
curl -s -o /etc/init.d/msiof-worker http://msiof.smellynose.com/init
chmod a+x /etc/init.d/msiof-worker
ln -s /etc/init.d/msiof-worker /etc/rc2.d/S99msiof-worker
service msiof-worker stop
service msiof-worker start

echo
echo "=== Done ==="
BASH;
});

$app->get('/init', function() {
		  return <<<INIT
#!/bin/sh
### BEGIN INIT INFO
# Provides:          msiof-worker
# Required-Start:    \$local_fs \$network \$named \$time \$syslog
# Required-Stop:     \$local_fs \$network \$named \$time \$syslog
# Default-Start:     2 3 4 5
# Default-Stop:      0 1 6
# Description:       msiof.smellynose.com worker
### END INIT INFO

SCRIPT=/etc/msiof/worker
RUNAS=root
NAME=msiof-worker

PIDFILE=/var/run/\$NAME.pid
LOGFILE=/var/log/\$NAME.log

start() {
  if [ -f \$PIDFILE ] && kill -0 $(cat \$PIDFILE); then
    echo 'Service already running' >&2
    return 1
  fi
  echo 'Starting service…' >&2
  local CMD="\$SCRIPT &> \"\$LOGFILE\" & echo \\\$!"
  su -c "\$CMD" \$RUNAS > "\$PIDFILE"
  echo 'Service started' >&2
}

stop() {
  if [ ! -f "\$PIDFILE" ] || ! kill -0 $(cat "\$PIDFILE"); then
    echo 'Service not running' >&2
    return 1
  fi
  echo 'Stopping service…' >&2
  kill -15 $(cat "\$PIDFILE") && rm -f "\$PIDFILE"
  echo 'Service stopped' >&2
}

status() {
        printf "%-50s" "Checking \$NAME..."
    if [ -f \$PIDFILE ]; then
        PID=\$(cat \$PIDFILE)
            if [ -z "\$(ps axf | grep \${PID} | grep -v grep)" ]; then
                printf "%s\n" "The process appears to be dead but pidfile still exists"
            else    
                echo "Running, the PID is \$PID"
            fi
    else
        printf "%s\n" "Service not running"
    fi
}


case "\$1" in
  start)
    start
    ;;
  stop)
    stop
    ;;
  status)
    status
    ;;
  uninstall)
    uninstall
    ;;
  restart)
    stop
    start
    ;;
  *)
    echo "Usage: \$0 {start|stop|status|restart}"
esac
INIT;
});


$app->get('/worker-init', function() {
		  return file_get_contents('../upstart/msiof-worker.conf');
});

$app->get('/worker-php', function() {
		  return file_get_contents('../worker/worker.php');
});

$app->get('/key', function(Application $app, Request $request) use($userId) {
		  $nextServerId = $app['predis']->incr('next_server_id');
		  $key = sha1($nextServerId.$userId);
		  $app['predis']->lpush("user:{$userId}:servers", $key);

		  return "key={$key}";
});

//Add server
$app->post('/server', function(Application $app, Request $request) {
		  $serverKey = $request->headers->get('X-Server-Key');
		  if (empty($serverKey)) {
					 return $app->json([
								'error' => 'Access Denied'
					 ], 403);
		  }

		  //@TODO: Check if serverKey is actually valid

		  $json = $request->getContent();
		  $jsonDecoded = json_decode($json, true);
		  $redisKey = "server:{$serverKey}";
		  $oldResult = $app['predis']->get($redisKey);
		  if (!empty($oldResult)) {
					 $oldResult = json_decode($oldResult, true);
					 $timeDiff = strtotime($jsonDecoded['time']) - strtotime($oldResult['time']);

					 foreach ($jsonDecoded['network'] as $interface => $info) {
								if ($interface == 'lo') {
										  continue;
								}

								$totalTxNew += $info['txbytes'];
								$totalRxNew += $info['rxbytes'];

								$totalTxOld += $oldResult['network'][$interface]['txbytes'];
								$totalRxOld += $oldResult['network'][$interface]['rxbytes'];
					 }

					 $txDiff = $totalTxNew - $totalTxOld;
					 $rxDiff = $totalRxNew - $totalRxOld;

					 $txBps = $txDiff / $timeDiff;
					 $rxBps = $rxDiff / $timeDiff;

					 $kilobitspersecond = ($txBps*8)/1000;
					 $jsonDecoded['network']['txkbps'] = $kilobitspersecond;
					 $jsonDecoded['network']['txtotal'] = $totalTxNew;

					 $kilobitspersecond = ($rxBps*8)/1000;
					 $jsonDecoded['network']['rxkbps'] = $kilobitspersecond;
					 $jsonDecoded['network']['rxtotal'] = $totalRxNew;
		  }
		  $jsonDecoded['lastupdated'] = time();

		  $predisResult = $app['predis']->set($redisKey, json_encode($jsonDecoded));

		  return $app->json([
					 'success' => 'Updated your server and stuff'
		  ]);
});

$app->run();
