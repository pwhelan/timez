<?php

require __DIR__.'/../vendor/autoload.php';


use Carbon\Carbon;

$m = new MongoClient(); // connect
$db = $m->timez; // get the database named "foo"
$timez = $db->tasks;


$app = new Slim\Slim();

$app->get('/task/(:id)', function($id = 0) use ($app, $timez) {
	if ($id) $timez->findOne(['_id-' => new MongoId($id)]);
	else $task = $timez->findOne(['active' => true]);
	
	if (empty($task)) {
		$app->render(404, [
			'error' => $id ? 
				'Not Found' : 
				'No Active Task'
		]);
	}
	
	$task['start'] = Carbon::createFromTimestamp(
			(int)explode(' ', (string)$task['start'])[1]
		)
		->format('c');
	
	$app->render(200, $task);
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
			'stop' => new MongoDate(strtotime('-'.abs($offset).' seconds'))
		]],
		['new' => true]
	);
	$task['start'] = Carbon::createFromTimestamp((int)explode(' ', (string)$task['start'])[1])->format('c');
	print json_encode($task);
});

$app->post('/task/(:name)', function($name = null) use ($app, $timez) {
	
	$task = [
		'start' 	=> new MongoDate(),
		'active'	=> true,
		'name'		=> $name ? $name : 'Task #'.time()
	];
	
	$timez->insert($task);
	$task['start'] = Carbon::createFromTimestamp(
			(int)explode(' ', (string)$task['start'])[1]
		)->format('c');
	
	$app->render(201, $task);
});

$app->post('/task/state/(:id)', function($id = null) use ($app, $timez) {
	
	$state = json_decode($app->request->getBody());
	$state->date = new MongoDate();
	
	$task = $timez->update(
		$id == null ?
			['active' => true] : 
			['_id' => new MongoId($id), 'active' => true],
		['$push' => ['states' => $state]]
	);
	
	$task['start'] = Carbon::createFromTimestamp(
			(int)explode(' ', (string)$task['start'])[1]
		)
		->format('c');
	
	$app->render(201, $task);
});

$app->post('/task/state/note/(:id)', function($id = null) use ($app, $timez) {
	
	$note = json_decode($app->request->getBody());
	$note->date = new MongoDate();
	
	$task = $timez->update(
		$id == null ?
			['active' => true] : 
			['_id' => new MongoId($id), 'active' => true],
		['$push' => ['notes' => $note]]
	);
	
	$app->render(201, []);
});


$app->view(new \JsonApiView());
$app->add(new \JsonApiMiddleware());

$app->run();

