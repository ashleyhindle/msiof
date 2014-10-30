<?php
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

require_once __DIR__.'/../vendor/autoload.php';

//apiKey to customerid mapping
$apiKeys = [
		'cheese' => 100
];

$serverKeys = [
		'ww1' => [
				'hostname' => 'ww1',
				'serverid' => 1
		]
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

$app->get('/', function(Application $app, Request $request) {
		$apiKey = $request->headers->get('X-Api-Key');
		if(empty($apiKey)) {
				return $app->json([
						'error' => 'Access Denied'
						], 403);
		} else {
				$app['predis']->hmset('dannyisadouche', [
						'doucheLevel' => 9000,
						'hairLevel' => 1,
						'cheese' => 'Yes',
						'awesome' => 'No'
						]);
				return 'Your apiKey is: ' . $apiKey;
		}
		
		return $app['predis']->get('toys'). " ---- " . $nextServerId;
});

//Add server
$app->post('/server', function(Application $app, Request $request) {
		$nextServerId = $app['predis']->incr('next_server_id');
		return 'no';
});

$app->get('/{stockcode}', function (Application $app, $stockcode) {
    $toys = json_decode($app['predis']->get('toys'), true);
    if (!isset($toys[$stockcode])) {
        $app->abort(404, "Stockcode {$stockcode} does not exist.");
    }
    return json_encode($toys[$stockcode]);
});

$app->run();
