<?php

namespace Timez;

use React\Http\Request;
use React\Http\Response;
use Silex\Application as BaseApplication;
use Silex\ControllerCollection;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpKernel\HttpKernelInterface;


class Application extends \React\Espresso\Application
{
	public function __invoke(Request $request, Response $response)
	{
		$content = "";
		$request->on('data', function($data) use (&$content, $request) {
			
			$content .= $data;
			if (strlen($data) >= $request->getHeaders()['Content-Length'])
			{
				$request->emit('flush');
			}
		});
		
		$request->on('flush', function() use (&$content, $request, $response, $result) {
			
			// Create Request with the full content.
			$fullrequest = SymfonyRequest::create(
				$request->getPath(),
				$request->getMethod(),
				$request->getQuery(),
				/*
				array_merge(
					$request->getQuery(),
					parse($content)
				)
				*/
				[], //$_COOKIES,
				[], // parse the goddamn input...
				$_SERVER,
				$content
			);
			
			$fullrequest->attributes->set('react.espresso.request', $request);
			$fullrequest->attributes->set('react.espresso.response', $response);
			
			$result = $this->handle($fullrequest, HttpKernelInterface::MASTER_REQUEST, true);
			
			$response->writeHead($result->getStatusCode(), $result->headers->all());
			$response->end($result->getContent());
		});
		
	}
}
