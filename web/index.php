<?php
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

require_once __DIR__.'/../vendor/autoload.php';

$userId = 100;

$serverKeys = [
		  sha1($hostname.'CHEESE_THIS_IS_JUST_A_TEST_SO_THIS_DOESNT_MATTER') => $userId
];

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

$nextUserId = $app['predis']->incr('next_user_id');

$app['predis']->set('apikey:cheese', $nextUserId);

$app->get('/aaaa', function(Application $app, Request $request) use($serverKeys) {
		  $app['predis']->lpush('user:100:servers', array_keys($serverKeys)[0]);
});

$app->get('/', function(Application $app, Request $request) {
		  $serverKeys = $app['predis']->lrange('user:100:servers', 0, -1);
		  echo ' <meta http-equiv="refresh" content="30"><pre>';
		  foreach ($serverKeys as $serverKey) {
					 $lastServerUpdate = json_decode($app['predis']->get("server:{$serverKey}"), true);
					 print_r($lastServerUpdate);
		  }
		  echo '</pre>';

		  return '';
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
					 $txDiff = $jsonDecoded['network']['eth0']['txbytes'] - $oldResult['network']['eth0']['txbytes'];
					 $timeDiff = strtotime($jsonDecoded['time']) - strtotime($oldResult['time']);
					 $bps = $txDiff / $timeDiff;
					 $kilobitspersecond = ($bps*8)/1000;
					 $jsonDecoded['network']['txkbps'] = $kilobitspersecond;
		  }

		  $predisResult = $app['predis']->set($redisKey, json_encode($jsonDecoded));
		  //$nextServerId = $app['predis']->incr('next_server_id');

		  return $app->json([
					 'success' => 'Updated your server and stuff'
		  ]);
});

$app->get('/{stockcode}', function (Application $app, $stockcode) {
		  $toys = json_decode($app['predis']->get('toys'), true);
		  if (!isset($toys[$stockcode])) {
					 $app->abort(404, "Stockcode {$stockcode} does not exist.");
		  }

		  return json_encode($toys[$stockcode]);
});

$app->run();
