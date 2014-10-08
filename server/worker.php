<?php


require_once __DIR__ .'/../vendor/autoload.php';


System_Daemon::setOption("appName", "timez-worker");  // Minimum configuration
System_Daemon::setOption("logLocation", __DIR__.'/log/timez-worker.log');
System_Daemon::setOption("appPidLocation", __DIR__.'/run/timez-worker/worker.pid');

System_Daemon::setOption("appRunAsUID", getmyuid());
System_Daemon::setOption("appRunAsGID", getmyuid());
System_Daemon::setOption("appDieOnIdentityCrisis", false);

System_Daemon::start();


$_SERVER['REQUEST_URI'] = '/worker';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_PORT'] = 80;

include __DIR__.'/public/index.php';

$app->run();
