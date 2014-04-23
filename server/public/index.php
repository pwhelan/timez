<?php

require_once __DIR__.'/../vendor/autoload.php';


use Carbon\Carbon;

$app = new Slim\App();


$app->group('/task', function() use ($app) {
		
	$app['view'] = new \JsonApiView($app);
	//$app->add(new \JsonApiMiddleware($app));
	
	$app->get('/(:id)', function($id = 0) use ($app) {
		
		if ($id) $app['tasks']->findOne(['_id-' => new MongoId($id)]);
		else $task = $app['tasks']->findOne(['active' => true]);
		
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
		
	})->conditions(['id' => '[a-zA-Z\d]+']);
	
	$app->post('/stop/(:id(/:offset))', function($id = 0, $offset = 0) use ($app) {
		
		if (ctype_digit($id) && $offset == 0) {
			$offset = $id;
			$id = 0;
		}
		
		$rc = $app['tasks']->update(
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
		
		print json_encode(['rc' => $rc]);
	});

	$app->post('/(:name)', function($name = null) use ($app) {
		
		$app['tasks']->update(
			['active' => true],
			['$set' => [
				'active' => false, 
				'stop' => new MongoDate(time())
			]]
		);
		
		$task = [
			'start' 	=> new MongoDate(),
			'active'	=> true,
			'name'		=> $name ? $name : 'Task #'.time()
		];
		
		$app['tasks']->insert($task);
		$task['start'] = Carbon::createFromTimestamp(
				(int)explode(' ', (string)$task['start'])[1]
			)->format('c');
		
		$app->render(201, $task);
	});
	
	$app->post('/state/(:id)', function($id = null) use ($app) {
		
		$state = json_decode($app['request']->getBody());
		$state->date = new MongoDate();
		
		$shm = shm_attach($app['shared']->id);
		
		if (shm_has_var($shm, $app['shared']->worker_pid))
		{
			$worker_pid = shm_get_var($shm, $app['shared']->worker_pid);
			
						
			if (shm_has_var($shm, $app['shared']->idletimes))
			{
				$idletimes = shm_get_var($shm, $app['shared']->idletimes);
				
				
				if (isset($idletimes[$app['request']->getIp()]))
				{
					$last_idle = $idletimes[$app['request']->getIp()];
					if ($state->idle <= $last_idle && $state->idle < (60 * 5))
					{
						posix_kill($worker_pid, SIGUSR1);
					}
				}
				
				$idletimes[$app['request']->getIp()] = $state->idle;
			}
			else
			{
				$idletimes = [$app['request']->getIp() => $state->idle];
			}
			
			shm_put_var($shm, $app['shared']->idletimes, $idletimes);
		}
		
		
		$rc = $app['tasks']->update(
			$id == null ?
				['active' => true] : 
				['_id' => new MongoId($id), 'active' => true],
			['$push' => ['states' => $state]]
		);
		
		if (!$rc['updatedExisting'])
		{
			$app->render(404, ["error" => "No running task"]);
		}
		
		$app->render(201, $rc);
	});
	
	$app->post('/note/(:id)', function($id = null) use ($app) {
		
		$note = json_decode($app['request']->getBody());
		$note->date = new MongoDate();
		
		$task = $app['tasks']->update(
			$id == null ?
				['active' => true] : 
				['_id' => new MongoId($id), 'active' => true],
			['$push' => ['notes' => $note]]
		);
		
		$app->render(201, []);
	});
	
	
});

$app->get('/worker', function() use ($app) {
	
	$timeout = 60;
	
	if (php_sapi_name() != 'cli')
	{
		die("WRONG SAPI=".php_sapi_name()."\n");
	}
	
	$shm = shm_attach($app['shared']->id);
	shm_put_var($shm, $app['shared']->worker_pid, getmypid());
	
	pcntl_signal(SIGUSR1, function() use ($app, $timeout) {
		pcntl_alarm($timeout);
	});
	
	pcntl_signal(SIGALRM, function() use ($app, $timeout) {
		
		$app['tasks']->update(
			['active' => true],
			['$set' => ['active' => false]]
		);
		pcntl_alarm($timeout);
	});
	
	pcntl_alarm($timeout);
	while (1):
		$info = [];
		sleep(5);
	endwhile;
	
});

$app->group('/web', function() use ($app) {
	
	$app['view'] = new Slim\Views\Layout(
		__DIR__.'/../app/views/',
		new Slim\Views\MtHaml(__DIR__.'/../app/views/'),
		'layout.haml'
	);
	
	
	//$app->add(new \Zeuxisoo\Whoops\Provider\Slim\WhoopsMiddleware);
	
	$app->get('/', function () use ($app) {
		$active = $app['tasks']->findOne(['active' => true]);
		$app->render('index.haml', ['active' => $active]);
	});
	
	$app->get('/history', function() use ($app) {
		$history = $app['tasks']
				->find([/*'active' => false*/], ['name', 'start', 'end'])
				->sort(['start' => -1]);
		$app->render('history.haml', ['history' => $history]);
	});
});

$app->config([
	'templates.path'=> __DIR__.'/../app/views/'
]);

$app['mongo'] = function($c) {
	return new MongoClient;
};

$app['mongo:timez'] = function($c) {
	return $c['mongo']->timez;
};

$app['tasks'] = function ($c) {
	return $c['mongo:timez']->tasks;
};

$app['shared'] = function($c) {
	return (object)[
		'id'		=> 0xf00f0000,
		'worker_pid'	=> 0xf00f0001,
		'idletimes'	=> 0xf00f0002
	];
};

$app->run();
