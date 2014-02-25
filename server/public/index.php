<?php

require __DIR__.'/../vendor/autoload.php';

$m = new MongoClient(); // connect
$db = $m->timez; // get the database named "foo"
$timez = $db->tasks;


$app = new Slim\Slim();

$app->get('/task/', function() use ($timez) {
	$task = $timez->findOne(['active' => true]);
	print json_encode($task);
});

$app->get('/task/:id', function($id) use ($timez) {
	$task = $timez->findOne(['_id' => new MongoId($id)]);
	print json_encode($task);
});

$app->post('/task/stop/(:id(/:offset))', function($id = 0, $offset = 0) use ($app, $timez) {
	
	if (ctype_digit($id) && $offset == 0) {
		$offset = $id;
		$id = 0;
	}
	
	$task = $timez->update(
		$id ? 
			(ctype_xdigit($id) && $id ?
				['_id' => new MongoId($id), 'active' => true] :
				(ctype_digit($id) ? 
					['name' => $id, 'active' => true] :
					['active' => true]
				)
			) :
			['active' => true],
		['$set' => [
			'active' => false, 
			'stop' => date('c', strtotime('-'.abs($offset).' seconds'))
		]],
		['new' => true]
	);
	print json_encode($task);
});

$app->post('/task/(:name)', function($name = null) use ($app, $timez) {
	
	$task = [
		'start' 	=> date('c'),
		'active'	=> true,
		'name'		=> $name ? $name : 'Task #'.time()
	];
	
	$timez->insert($task);
	print json_encode($task);
});

$app->post('/task/state/(:id)', function($id = null) use ($app, $timez) {
	
	print "ID = {$id}\n";
	$state = json_decode($app->request->getBody());
	$state->date = date('c');
	
	$task = $timez->update(
		$id == null ?
			['active' => true] : 
			['_id' => new MongoId($id), 'active' => true],
		['$push' => ['states' => $state]]
	);
	
	print json_encode($task);
});

$app->run();

