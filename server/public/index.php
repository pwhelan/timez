<?php

require __DIR__.'/../vendor/autoload.php';


use Carbon\Carbon;

$app = new Slim\Slim();


$app->group('/task', function() use ($app) {
	
	$app->view(new \JsonApiView());
	$app->add(new \JsonApiMiddleware());
	
	$app->get('/(:id)', function($id = 0) use ($app) {
		if ($id) $app->tasks->findOne(['_id-' => new MongoId($id)]);
		else $task = $app->tasks->findOne(['active' => true]);
		
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
	
	$app->post('/stop/(:id(/:offset))', function($id = 0, $offset = 0) use ($app) {
		
		if (ctype_digit($id) && $offset == 0) {
			$offset = $id;
			$id = 0;
		}
		
		$task = $app->tasks->update(
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

	$app->post('/(:name)', function($name = null) use ($app) {
		
		$task = [
			'start' 	=> new MongoDate(),
			'active'	=> true,
			'name'		=> $name ? $name : 'Task #'.time()
		];
		
		$app->tasks->insert($task);
		$task['start'] = Carbon::createFromTimestamp(
				(int)explode(' ', (string)$task['start'])[1]
			)->format('c');
		
		$app->render(201, $task);
	});
	
	$app->post('/state/(:id)', function($id = null) use ($app) {
		
		$state = json_decode($app->request->getBody());
		$state->date = new MongoDate();
		
		$task = $app->tasks->update(
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
	
	$app->post('/note/(:id)', function($id = null) use ($app) {
		
		$note = json_decode($app->request->getBody());
		$note->date = new MongoDate();
		
		$task = $app->tasks->update(
			$id == null ?
				['active' => true] : 
				['_id' => new MongoId($id), 'active' => true],
			['$push' => ['notes' => $note]]
		);
		
		$app->render(201, []);
	});
});


$app->group('/web', function() use ($app) {
	
	$app->view(new Slim\Views\Layout(
		new Slim\Views\MtHaml,
		'layout.haml'
	));
	
	$app->add(new \Zeuxisoo\Whoops\Provider\Slim\WhoopsMiddleware);
	
	$app->get('/', function () use ($app) {
		$active = $app->tasks->findOne(['active' => true]);
		$app->render('index.haml', ['active' => $active]);
	});
	
	$app->get('/history', function() use ($app) {
		$history = $app->tasks
				->find([/*'active' => false*/], ['name', 'start', 'end'])
				->sort(['start' => -1]);
		$app->render('history.haml', ['history' => $history]);
	});
});

$app->config([
	'templates.path'=> __DIR__.'/../app/views/'
]);

$app->container->singleton('tasks', function () {
	$m = new MongoClient(); // connect
	$db = $m->timez; // get the database named "foo"
	return $db->tasks;
});

$app->run();
