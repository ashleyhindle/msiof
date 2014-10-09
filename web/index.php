<?php
require_once __DIR__.'/../vendor/autoload.php';

$app = new Silex\Application();
// Please set to false in a production environment
$app['debug'] = true;
$app->register(new Predis\Silex\ClientServiceProvider(), [
		'predis.parameters' => 'tcp://127.0.0.1:6379',
		'predis.options'    => [
		'prefix'  => 'msiof:',
		'profile' => '3.0',
		],
]);

$toys = array(
    '00001'=> array(
        'name' => 'Racing Car',
        'quantity' => '53',
        'description' => '...',
        'image' => 'racing_car.jpg',
    ),
    '00002' => array(
        'name' => 'Raspberry Pi',
        'quantity' => '13',
        'description' => '...',
        'image' => 'raspberry_pi.jpg',
    ),
);

$app['predis']->set('toys', json_encode($toys));

$app->get('/', function() use ($toys) {
    return $app['predis']->get('toys');
});

$app->get('/{stockcode}', function (Silex\Application $app, $stockcode) {
    $toys = json_decode($app['predis']->get('toys'), true);
    if (!isset($toys[$stockcode])) {
        $app->abort(404, "Stockcode {$stockcode} does not exist.");
    }
    return json_encode($toys[$stockcode]);
});

$app->run();
