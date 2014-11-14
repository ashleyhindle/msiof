<?php
use Silex\Application;
use Silex\Provider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

require_once __DIR__.'/../vendor/autoload.php';

$env = getenv('APP_ENV') ?: 'prod';
$userId = 100;
$latestWorkerVersion = 1.1;

$app = new Application();
// Map api keys to userids
$apiKeys = [
		  'cheese' => 100,
		  'anomander' => 101,
		  'demo' => 102
];

$app->register(new Silex\Provider\TwigServiceProvider(), array(
		  'twig.path' => '../views',
));

$app->register(new Provider\DoctrineServiceProvider());
$app->register(new Provider\SecurityServiceProvider());
$app->register(new Provider\RememberMeServiceProvider());
$app->register(new Provider\SessionServiceProvider());
$app->register(new Provider\ServiceControllerServiceProvider());
$app->register(new Provider\UrlGeneratorServiceProvider());
$app->register(new Provider\SwiftmailerServiceProvider());
$app->register(new Predis\Silex\ClientServiceProvider());
// Predis config should go in config/dev.json and config/prod.json



$simpleUserProvider = new SimpleUser\UserServiceProvider();
$app->register($simpleUserProvider);
$app->mount('/account', $simpleUserProvider);


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
					 $server['issues'] = [
								'loadavg' => ( $server['system']['loadavg'] >= $server['system']['cpu']['cores'] ),
								'disk' => ( $server['disk']['/']['free'] <= ($server['disk']['/']['total']*0.15) ),
								'mem' => ( ( ( ($server['mem']['memtotal'] - $server['mem']['memfree'] - $server['mem']['cached'] - $server['mem']['buffers']) / $server['mem']['memtotal'] ) * 100 ) >= 85 ),
								'lastupdated' => ( $server['lastupdated'] < (  time() - (60*5) ))
					 ];
					 $server['hasIssues'] = array_sum($server['issues']);
					 $server['outOfDate'] = ($server['workerversion'] < $latestWorkerVersion);
					 unset($server['conns']);
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

		  return $app['twig']->render('index.twig', [
					 'installUrl' => "{$protocol}{$_SERVER['SERVER_NAME']}/install"
		  ]);
});

//Add server

$app->get('/install', function(Application $app) {
		  return $app->sendFile('../worker/install');
});

$app->get('/init', function(Application $app) {
		  return $app->sendFile('../worker/init');
});

$app->get('/worker-php', function(Application $app) {
		  return $app->sendFile('../worker/worker.php');
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

		  return $app['twig']->render('dashboard.twig', [
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

		  $jsonDecoded['mem']['percentage'] = [
					 'usage' => round(( ( $jsonDecoded['mem']['memtotal'] - $jsonDecoded['mem']['memfree'] - $jsonDecoded['mem']['cached'] - $jsonDecoded['mem']['buffers']) / $jsonDecoded['mem']['memtotal'] ) * 100, 1)
		  ];

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

					 $oldCpu = $oldResult['cpu']['cpu'];
					 $newCpu = $jsonDecoded['cpu']['cpu'];

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
					 $jsonDecoded['cpu']['percentage']['usage'] = round((($cpuDiff['user'] + $cpuDiff['nice'] + $cpuDiff['system']) / $total) * 100, 1);

					 $jsonDecoded['disk']['percentage'] = [
								'usage' => round((($jsonDecoded['disk']['/']['total'] - $jsonDecoded['disk']['/']['free']) / $jsonDecoded['disk']['/']['total'] ) * 100, 1)
					 ];

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
										  'txkbps' => $txKbps,
										  'totalkbps' => $rxKbps + $txKbps
										  ];
					 }
		  }

		  $jsonDecoded['lastupdated'] = time();
		  $jsonDecoded['publicip'] = $_SERVER['REMOTE_ADDR'];
		  $jsonDecoded['conns']['total'] = array_sum($jsonDecoded['conns']);

		  $jsonDecoded['system']['loadavg'] = floatval($jsonDecoded['system']['loadavg']);

		  $predisResult = $app['predis']->set($redisKey, json_encode($jsonDecoded));

		  return $app->json([
					 'success' => 'Updated your server and stuff'
		  ]);
});


$app['user.options'] = [
		  'templates' => [
					 'layout' => 'layout.twig',
					 'register' => '/account/register.twig',
					 'register-confirmation-sent' => '/account/register-confirmation-sent.twig',
					 'login' => '/account/login.twig',
					 'login-confirmation-needed' => '/account/login-confirmation-needed.twig',
					 'forgot-password' => '/account/forgot-password.twig',
					 'reset-password' => '/account/reset-password.twig',
					 'view' => '/account/view.twig',
					 'edit' => '/account/edit.twig',
					 'list' => '/account/list.twig',
		  ],
		  'mailer' => [
					 'enabled' => true,
					 'fromEmail' => [
								'address' => 'noreply@' . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : gethostname()),
								'name' => null,
					 ]
		  ],
		  'emailConfirmation' => [
					 'required' => true,
		  ]
];

$app['security.firewalls'] = [
		  'login' => [
					 'pattern' => '^/account/login$',
		  ],
		  'index' => [
					 'pattern' => '^/$',
		  ],
		  'demo' => [
					 'pattern' => '^/demo$',
		  ],
		  'api' => [
					 'pattern' => '^/servers/.*$',
		  ],
		  'install' => [
					 'pattern' => '^/install$',
		  ],
		  'init' => [
					 'pattern' => '^/init$',
		  ],
		  'key' => [
					 'pattern' => '^/key$',
		  ],
		  'workerphp' => [
					 'pattern' => '^/worker-php$',
		  ],
		  'workerapi' => [
					 'pattern' => '^/server$',
		  ],
		  'setdemo' => [
					 'pattern' => '^/setdemo$',
		  ],
		  'secured_area' => [
					 'pattern' => '^.*$',
					 'remember_me' => [],
					 'form' => [
								'login_path' => '/account/login',
								'check_path' => '/account/login_check',
					 ],
					 'logout' => [
								'logout_path' => '/account/logout',
					 ],
					 'users' => $app->share(function($app) {
								return $app['user.manager'];
					 })
		  ],
];


$app->register(new Igorw\Silex\ConfigServiceProvider(__DIR__."/../config/{$env}.json"));

$app->run();
