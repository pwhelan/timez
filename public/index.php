<?php

require_once __DIR__.'/../vendor/autoload.php';

use Silex\Application as App;
use React\Espresso\Stack;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Carbon\Carbon;

date_default_timezone_set('GMT');


if (php_sapi_name() == 'cli' && basename($_SERVER['SCRIPT_NAME']) == 'index.php')
{
	$IsReact = true;
}
else
{
	$IsReact = false;
}


if ($IsReact)
{
	require __DIR__.'/../app/Application.php';
	$app = new Timez\Application();
}
else
{
	$app = new App();
}

$app['debug'] = true;


class JsonApiView
{
	protected $pretty_print = false;
	
	
	public function __construct($options = [])
	{
		if (isset($options['pretty_print']))
		{
			$this->pretty_print = $options['pretty_print'];
		}
	}
	
	public function render($code = 200, $result = [])
	{
		return new Response(
			json_encode($result, $this->pretty_print ? JSON_PRETTY_PRINT : 0),
			$code,
			['Content-Type'	=> 'application/json']
		);
	}
}


$task = $app['controllers_factory'];

$task->before(function() use ($app) {
	
	$app['view'] = function() { 
		return new \JsonApiView(['pretty_print' => true]); 
	};
	
});


$task->delete('/{id}/{offset}', function($id = 0, $offset = 0) use ($app) {
	
	if (ctype_digit($id) && $offset == 0) {
		$offset = $id;
		$id = 0;
	}
	
	$rc = $app['tasks']->update(
		$id == 'current' ? 
			['active' => true] :
			(ctype_xdigit($id) && $id ?
				['_id' => new MongoId($id), 'active' => true] :
				(ctype_digit($id) ? 
					['name' => $id, 'active' => true] :
					['active' => true]
				)
			),
		['$set' => [
			'active' => false, 
			'stop' => new MongoDate(strtotime('-'.abs($offset).' seconds'))
		]],
		['new' => true]
	);
	
	return $app['view']->render(200, ['rc' => $rc]);
	
})->value('offset', 0)->value('id', 'current');


$task->post('/{id}/note', function(App $app, Request $request, $id) {
	
	$note = json_decode($request->getContent());
	$note->date = new MongoDate();
	
	$task = $app['tasks']->update(
		$id == 'current' ?
			['active' => true] : 
			['_id' => new MongoId($id), 'active' => true],
		['$push' => ['notes' => $note]]
	);
	
	return $app['view']->render(201, ['rc' => true]);
	
})->value('id', 'current');


$task->post('/{id}/state', function(App $app, Request $request, $id) {
	
	$state = json_decode($request->getContent());
	$state->date = new MongoDate();
	
	
	$shm = shm_attach($app['shared']->id);
	if (isset($state->idle) && shm_has_var($shm, $app['shared']->worker_pid))
	{
		$worker_pid = shm_get_var($shm, $app['shared']->worker_pid);
		if (shm_has_var($shm, $app['shared']->idletimes))
		{
			$idletimes = shm_get_var($shm, $app['shared']->idletimes);
			
			
			if (isset($idletimes[$request->getClientIp()]))
			{
				$last_idle = $idletimes[$request->getClientIp()];
				if ($state->idle <= $last_idle && $state->idle < ($app['timeout']))
				{
					$app['periodic']->reset();
				}
			}
			
			$idletimes[$request->getClientIp()] = $state->idle;
		}
		else
		{
			$idletimes = [$request->getClientIp() => $state->idle];
		}
		
		shm_put_var($shm, $app['shared']->idletimes, $idletimes);
	}
	
	
	$rc = $app['tasks']->update(
		$id == 'current' ?
			['active' => true] : 
			['_id' => new MongoId($id), 'active' => true],
		['$push' => ['states' => $state]]
	);
	
	if (!$rc['updatedExisting'])
	{
		return $app['view']->render(404, ["error" => "No running task"]);
	}
	
	return $app['view']->render(201, $rc);
	
})->value('id', 'current');


$task->post('/{name}', function(App $app, $name) {
	
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
	
	return $app['view']->render(201, $task);
});


$task->get('/{id}', function(App $app, $id) {
	
	$aggregate = [
		['$match'	=> $id == 'current' ?
			['active' => true] :
			['_id' => new MongoId($id)]
		],
		['$project' => [
			'name'	=> 1,
			'start'	=> 1,
			'stop'	=> ['$ifNull' => ['$stop', null]],
			'active'=> 1,
			'states'=> ['$ifNull' => ['$states', [ null ]
			]]
		]],
		['$unwind' => '$states'],
		['$group' => [
			'_id'	=> '$_id',
			'name'	=> ['$addToSet' => '$name'],
			'start'	=> ['$addToSet' => '$start'],
			'stop'	=> ['$addToSet' => '$stop'],
			'active'=> ['$addToSet' => '$active'],
			'states'=> ['$sum' => 1]
		]],
		['$unwind' => '$start'],
		['$unwind' => '$name'],
		['$unwind' => '$stop'],
		['$unwind' => '$active']
	];
	
	
	$results = $app['tasks']->aggregate($aggregate);
	if ($results['ok'] != 1)
	{
		return $app['view']->render(400, ['error' => 'bad request']);
	}
	
	if (count($results['result']) < 1)
	{
		return $app['view']->render(404, [
			'error' => $id == 'current' ? 
				'Not Found..' : 
				'No Active Task'
		]);
	}
	
	
	$task = $results['result'][0];
	
	$task['start'] = Carbon::createFromTimestamp(
			(int)explode(' ', (string)$task['start'])[1]
		)
		->format('c');
	
	if (isset($task['stop']) && $task['stop'])
	{
		$task['stop'] = Carbon::createFromTimestamp(
				(int)explode(' ', (string)$task['stop'])[1]
			)
			->format('c');
	}
	
	return $app['view']->render(200, $task);
	
})->value('id', 'current');


$app->get('/worker', function(App $app) {
	
	if (php_sapi_name() != 'cli')
	{
		die("WRONG SAPI=".php_sapi_name()."\n");
	}
	
	$shm = shm_attach($app['shared']->id);
	shm_put_var($shm, $app['shared']->worker_pid, getmypid());
	
	
	$app['periodic']->run(function() use ($app) {
		
		$shm = shm_attach($app['shared']->id);
		$minidle = $app['timeout'];
		
			
		if (shm_has_var($shm, $app['shared']->idletimes))
		{
			$idletimes = shm_get_var($shm, $app['shared']->idletimes);
			$minidle = array_reduce(
				$idletimes,
				function($result, $idletime) {
					if ($idletime < $result) {
						$result = $idletime;
					}
					return $result;
				},
				$app['timeout']
			);
		}
		
		$app['tasks']->update(
			['active' => true],
			['$set' => [
				'active' => false,
				'stop' => new MongoDate(strtotime('-'.abs($minidle).' seconds'))
			]]
		);
	});
	
	return "Running\n";
});


class MtHamlView
{
	/** @var $env
	 * MtHaml Environment context.
	 */
	protected $env;
	
	/** @var $executor
	 * MtHaml PHP Executor.
	 */
	protected $executor;
	
	/** @var $templateDirectory
	 * Base directory for templates.
	 */
	protected $templateDirectory;
	
	
	/**
	 * Constructor
	 * @param  string $templateDirectory Path to template directory
	 * @param  array  $items             Initialize set with these items
	 * @api
	 */
	public function __construct($templateDirectory, array $items = array())
	{
		$this->env = new MtHaml\Environment('php');
		$this->executor = new MtHaml\Support\Php\Executor($this->env, [
			'cache'			=> sys_get_temp_dir().'/haml',
			'enable_escaper'	=> true,
			'escape_attrs'		=> false
		]);
		$this->templateDirectory = $templateDirectory;
	}
	
	/**
	 * Renders a template using Lavender
	 *
	 * @see View::render()
	 * @param string $template The template name specified in Slim::render()
	 * @return string
	 */	
	public function render($template, array $data = null)
	{
		// Compiles and executes the HAML template, with variables given as second
		// argument
		return $this->executor->render($this->templateDirectory .'/'. $template .'.haml', $data);
	}
}


class Layout
{
	protected $View;
	protected $Layout;
	
	
	public function __construct($templatesDirectory, $View, $Layout)
	{
		$this->View = $View;
		$this->Layout = $Layout;
	}
	
	/**
	* Renders Templates with layouts.
	*
	* @see View::render()
	* @throws RuntimeException If MtHaml lib directory does not exist.
	* @param string $template The template name specified in Slim::render()
	* @return string
	*/	
	public function render($template, array $data = null)
	{
		return $this->View->render(
			$this->Layout, 
			array_merge(
				$data, 
				['yield' => $this->View->render($template, $data)]
			)
		);
	}
}


$web = $app['controllers_factory'];


$web->before(function() use ($app) {
	
	$app['view'] = new Layout(
		__DIR__.'/../app/views/',
		//new LavenderView(__DIR__.'/../app/views/'),
		new MtHamlView(__DIR__.'/../app/views/'),
		'layout'
	);
	
});


$web->get('/{taskid}', function (App $app) {
	$active = $app['tasks']->findOne(['active' => true]);
	return $app['view']->render('index', ['active' => $active]);
})->value('taskid', null);


$web->get('/history', function(App $app) {
	$history = $app['tasks']
		->find(['active' => false], ['name', 'start', 'end'])
		->sort(['start' => -1]);
	
	return $app['view']->render('history', ['history' => $history]);
});


$app->mount('/tasks', $task);
$app->mount('/web', $web);

// https://github.com/silexphp/Silex/issues/149#issuecomment-1817904
if (isset($_SERVER['REQUEST_URI']))
{
	$_SERVER['REQUEST_URI'] = rtrim($_SERVER['REQUEST_URI'], '/');
	//$_SERVER['REQUEST_URI'] = rtrim($_SERVER['REQUEST_URI'], '/') . '/';
}

$app->match('/{path}', function(Request $request, $path) {
	
	$filepath = realpath(__DIR__.'/'.$request->getPathInfo());
	if (substr($filepath, 0, strlen(__DIR__)) == __DIR__)
	{
		return new Response(file_get_contents($filepath), 200);
	}
	
	throw new NotFoundHttpException();
	
})->assert('path', '.+');


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

$app['timeout'] = function($c) {
	return 5 * 60;
};


class PeriodicReact
{
	protected $stack;
	protected $app;
	protected $timer = null;
	protected $callback;
	
	
	public function __construct(Stack $stack)
	{
		$this->stack = $stack;
		$this->app = $stack['app'];
	}
	
	public function reset()
	{
		if ($this->timer)
		{
			$this->timer->cancel();
		}
		
		$this->timer = $this->stack['loop']->addTimer(
			$this->app['timeout'], 
			$this->callback
		);
	}
	
	public function run(Callable $callback)
	{
		$this->callback = $callback;
		$this->reset();
	}
}

class PeriodicAlarm
{
	protected $app;
	
	
	public function __construct(App $app)
	{
		$this->app = $app;
	}
	
	public function reset()
	{
		$shm = shm_attach($this->app['shared']->id);
		if (shm_has_var($shm, $this->app['shared']->worker_pid))
		{
			$worker_pid = shm_get_var($shm, $this->app['shared']->worker_pid);
			posix_kill($worker_pid, SIGUSR1);
		}
	}
	
	public function run(Callable $callback)
	{
		pcntl_signal(SIGUSR1, function() {
			pcntl_alarm($this->app['timeout']);
		});
		
		pcntl_signal(SIGALRM, function() use ($callback) {
			$callback();
			pcntl_alarm($this->app['timeout']);
		});
		
		pcntl_alarm($this->app['timeout']);
		while (1):
			sleep(5);
		endwhile;
	}
}


if ($IsReact)
{
	$stack = new Stack($app);
	$app['periodic'] = new PeriodicReact($stack);
	$app->run(Request::create('/worker'));
	$stack->listen(9137, "0.0.0.0");
}
else
{
	$app['periodic'] = new PeriodicAlarm($app);
	$app->run();
}
