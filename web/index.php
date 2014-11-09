<?php
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

require_once __DIR__.'/../vendor/autoload.php';

$userId = 100;
$latestWorkerVersion = 1.1;

$app = new Application();
// Map api keys to userids
$apiKeys = [
		  'cheese' => 100,
		  'anomander' => 101,
		  'demo' => 102
];
// Please set to false in a production environment
//$app['debug'] = true;

$app->register(new Silex\Provider\TwigServiceProvider(), array(
		  'twig.path' => '../views',
));

$app->register(new Predis\Silex\ClientServiceProvider(), [
		  'predis.parameters' => 'tcp://127.0.0.1:6379',
		  'predis.options'    => [
					 'prefix'  => 'msiof:',
					 'profile' => '3.0',
		  ],
]);


$app->get('/servers/{apiKey}', function(Application $app, Request $request) use($apiKeys, $latestWorkerVersion) {
		  $apiKey = $request->get('apiKey');
		  if (empty($apiKey)) {
					 return $app->json([
								'error' => 'Access Denied'
					 ], 403);
		  }

		  $userId = $apiKeys[$apiKey];
		  if (empty($userId)) {
					 return $app->json([
								'error' => 'Invalid apiKey'
					 ], 403);
		  }

		  $serverKeys = $app['predis']->lrange("user:{$userId}:servers", 0, -1);
		  $servers = [];

		  foreach ($serverKeys as $serverKey) {
					 $server = json_decode($app['predis']->get("server:{$serverKey}"), true);
					 $server['msiofTime'] = date('H:i:s', $server['lastupdated']);
					 $server['hasIssues'] = (
								$server['loadavg'] >= $server['system']['cpu']['cores'] ||
								$server['disk']['/']['free'] <= ($server['disk']['/']['total']*0.15) ||
								( ( ($server['mem']['memtotal'] - $server['mem']['memfree'] - $server['mem']['cached'] - $server['mem']['buffers']) / $server['mem']['memtotal'] ) * 100 ) >= 85
					 );
					 $server['outOfDate'] = ($server['workerversion'] < $latestWorkerVersion);
					 $servers[] = $server;
		  }

		  return $app->json($servers);
});

/** Get servers for user 100, and add some of them to the demo user too **/
$app->get('/setdemo', function(Application $app) use ($apiKeys) {
		  $serverKeys = $app['predis']->lrange("user:100:servers", 0, -1);
		  $servers = [];
		  $app['predis']->del([
					 'user:102:servers'
		  ]);

		  foreach ($serverKeys as $serverKey) {
					 $server = json_decode($app['predis']->get("server:{$serverKey}"), true);
					 if (substr($server['name'], 0, 2) == 'ww') {
								$app['predis']->lpush('user:102:servers', $serverKey);
					 }
		  }

		 return 'Done';
});

$app->post('/', function(Application $app, Request $request) {
		  return $app->redirect('/'.$request->get('apiKey'));
});

$app->get('/', function(Application $app, Request $request) {
		  $protocol = (!empty($_SERVER['HTTPS'])) ? 'https://' : 'http://';

		  return $app['twig']->render('indexNoKey.twig', [
					 'installUrl' => "{$protocol}{$_SERVER['SERVER_NAME']}/install"
		  ]);
});

//Add server

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
		  $app['predis']->set('server:'.$key, true);

		  return "key={$key}";
});

$app->get('/{apiKey}', function(Application $app, Request $request) use($latestWorkerVersion) {
		  $protocol = (!empty($_SERVER['HTTPS'])) ? 'https://' : 'http://';

		  return $app['twig']->render('index.twig', [
					 'installUrl' => "{$protocol}{$_SERVER['SERVER_NAME']}/install",
					 'apiKey' => $request->get('apiKey'),
					 'latestWorkerVersion' => $latestWorkerVersion
		  ]);
});


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
					 if ($oldResult === false) {
								$oldResult = [];
					 }

					 $timeDiff = strtotime($jsonDecoded['time']) - strtotime($oldResult['time']);
					 $totalTxNew = 0;
					 $totalRxNew = 0;
					 $totalTxOld = 0;
					 $totalRxOld = 0;

					 $oldCpu = $oldResult['cpu'];
					 $newCpu = $jsonDecoded['cpu'];
					 $cpuDiff = [
								'user' => $newCpu['user'] - $oldCpu['user'],
								'nice' => $newCpu['nice'] - $oldCpu['nice'],
								'system' => $newCpu['system'] - $oldCpu['system'],
								'idle' => $newCpu['idle'] - $oldCpu['idle']
					 ];

					 $total = array_sum($cpuDiff);
					 foreach ($cpuDiff as $type => $diff) {
								$jsonDecoded['cpu']['percentage'][$type] = round($diff / $total * 100, 1);
					 }

					 foreach ($jsonDecoded['network'] as $interface => $info) {
								if ($interface == 'lo') {
										  continue;
								}

								$totalTxNew += $info['txbytes'];
								$totalRxNew += $info['rxbytes'];

								$totalTxOld += $oldResult['network'][$interface]['txbytes'];
								$totalRxOld += $oldResult['network'][$interface]['rxbytes'];

								$txDiff = $info['txbytes'] - $oldResult['network'][$interface]['txbytes'];
								$rxDiff = $info['rxbytes'] - $oldResult['network'][$interface]['rxbytes'];

								$txBps = $txDiff / $timeDiff;
								$rxBps = $rxDiff / $timeDiff;

								$txKbps = ($txBps*8)/1000;
								$rxKbps = ($rxBps*8)/1000;

								$jsonDecoded['network'][$interface]['txkbps'] = $txKbps;
								$jsonDecoded['network'][$interface]['rxkbps'] = $rxKbps;
					 }


					 if ($totalTxNew) {
								$txDiff = $totalTxNew - $totalTxOld;
								$rxDiff = $totalRxNew - $totalRxOld;

								$txBps = $txDiff / $timeDiff;
								$rxBps = $rxDiff / $timeDiff;

								$txKbps = ($txBps*8)/1000;
								$rxKbps = ($rxBps*8)/1000;

								$jsonDecoded['network']['total'] = [
										  'txbytes' => $totalTxNew,
										  'rxbytes' => $totalRxNew,
										  'rxkbps' => $rxKbps,
										  'txkbps' => $txKbps
										  ];
					 }
		  }

		  $jsonDecoded['lastupdated'] = time();
		  $jsonDecoded['publicip'] = $_SERVER['REMOTE_ADDR'];

		  $predisResult = $app['predis']->set($redisKey, json_encode($jsonDecoded));

		  return $app->json([
					 'success' => 'Updated your server and stuff'
		  ]);
});

$app->run();
