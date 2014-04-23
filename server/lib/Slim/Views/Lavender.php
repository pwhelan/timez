<?php

/**
 * Slim - a micro PHP 5 framework
 *
 * @author      Josh Lockhart
 * @link        http://www.slimframework.com
 * @copyright   2011 Josh Lockhart
 *
 * MIT LICENSE
 *
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the
 * "Software"), to deal in the Software without restriction, including
 * without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject to
 * the following conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
 * LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
 * OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
 * WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */
namespace Slim\Views;

/**
 * Lavender
 *
 * Lavender Template adaptor for Slim 3.0
 * @package Slim
 * @author  Phillip Whelan <pwhelan@mixxx.org>
 */
class Lavender extends \Slim\View
{
	/**
	 * Constructor
	 * @param  string $templateDirectory Path to template directory
	 * @param  array  $items             Initialize set with these items
	 * @api
	 */
	public function __construct($templateDirectory, array $items = array())
	{
		\Lavender::config([
			'view_dir'	=> $templateDirectory,
			'file_extension'=> 'jade',
			'handle_errors'	=> true
		]);
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
		return \Lavender::view($template)->compile($data);
	}
}
