<?php

namespace Timez;

use React\Http\Request;
use React\Http\Response;
use Silex\Application as BaseApplication;
use Silex\ControllerCollection;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpKernel\HttpKernelInterface;

use h4cc\Multipart\ParserSelector;


class Application extends \React\Espresso\Application
{
	public function __invoke(Request $request, Response $response)
	{
		$content = "";
		
		
		$request->on('data', function($data) use (&$content, &$curlen, $request) {
			
			$headers = $request->getHeaders();
			
			if (isset($headers['Transfer-Encoding']) && $headers['Transfer-Encoding'] == 'chunked')
			{
				
			}
			else
			{
				if (!isset($headers['Content-Length']))
				{
					$request->emit('flush');
					return;
				}
				
				$content .= $data;
				if (strlen($data) >= $headers['Content-Length'])
				{
					$request->emit('flush');
				}
			}
		});
		
		$request->on('flush', function() use (&$content, $request, $response) {
			
			$sfrequest = $this->buildSfRequest($request, $response, $content);
			$result = $this->handle($sfrequest, HttpKernelInterface::MASTER_REQUEST, true);
			
			$response->writeHead($result->getStatusCode(), $result->headers->all());
			$response->end($result->getContent());
		});
	}
	
	protected function buildSfRequest(Request $request, Response $response, &$content)
	{
		list ($post, $files) = $this->parseBody(
			$request->getMethod(),
			$request->getHeaders(), 
			$content
		);
		
		if (empty($post))
		{
			$post = [];
		}
		
		// Create Request with the full content.
		$sfrequest = SymfonyRequest::create(
			$request->getPath(),
			$request->getMethod(),
			array_merge(
				$request->getQuery(),
				$post
			),
			[], // $_COOKIES ...
			[], // $_FILES ...
			$_SERVER,
			$content
		);
		
		$sfrequest->attributes->set('react.espresso.request', $request);
		$sfrequest->attributes->set('react.espresso.response', $response);
		
		return $sfrequest;
	}
	
	protected function parseBody($method, $hdr, &$content)
	{
		$post = [];
		$files = [];
		
		
		$ContentType = strtolower(
			strpos($hdr['Content-Type'], ';') === FALSE ?
				@$hdr['Content-Type'] :
				substr($hdr['Content-Type'], 0, strpos($hdr['Content-Type'], ';'))
		);
		
		
		switch($ContentType) 
		{
		case 'multipart/form-data':
			$selector = new ParserSelector();
			$parser = $selector->getParserForContentType($hdr['Content-Type']);
			$multipart = $parser->parse($content);
			
			
			foreach ($multipart as $part)
			{
				if (isset($part['dispositions']))
				{
					$disposition = $part['dispositions'];
					if (!isset($disposition['filename']))
					{
						$post[$disposition['name']] = $part['body'];
					}
					else
					{
						throw new \Exception('WIP: FILES via MultiPart');
					}
				}
			}
			break;
			
		case 'application/x-www-form-urlencoded':
			case (!in_array($method, ['GET', 'HEAD'])):
			parse_str($content, $post);
			break;
			
		case 'application/json':
			$post = json_decode($content);
			break;
		}
		
		return [$post, $files];
	}
			
}
