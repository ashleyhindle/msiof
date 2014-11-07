<?php
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

require_once __DIR__.'/../vendor/autoload.php';

$userId = 100;

$app = new Application();
// Please set to false in a production environment
//$app['debug'] = true;

$app->register(new Silex\Provider\TwigServiceProvider(), array(
		  'twig.path' => __DIR__.'/views',
));

$app->register(new Predis\Silex\ClientServiceProvider(), [
		  'predis.parameters' => 'tcp://127.0.0.1:6379',
		  'predis.options'    => [
					 'prefix'  => 'msiof:',
					 'profile' => '3.0',
		  ],
]);

//$nextUserId = $app['predis']->incr('next_user_id');

$protocol = (stripos($_SERVER['SERVER_PROTOCOL'], 'https') !== false) ? 'https://' : 'http://';

$app->get('/', function(Application $app, Request $request) use ($protocol) {
		  echo "curl -s {$protocol}{$_SERVER['SERVER_NAME']}/install | bash<hr>";
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
		  readfile('../worker/install');

		  return '';
});

$app->get('/init', function() {
		  readfile('../worker/init');

		  return '';
});

$app->get('/worker-php', function() {
		  readfile('../worker/worker.php');

		  return '';
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
		  $jsonDecoded['publicip'] = $_SERVER['REMOTE_ADDR'];

		  $predisResult = $app['predis']->set($redisKey, json_encode($jsonDecoded));

		  return $app->json([
					 'success' => 'Updated your server and stuff'
		  ]);
});

$app->run();
