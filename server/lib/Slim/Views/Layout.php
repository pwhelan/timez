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
 * MTHamlView
 *
 * The HamlView is a Custom View class that renders templates using the
 * HAML template language (http://haml-lang.com/) through the use of
 * MTHaml (https://github.com/arnaud-lb/MtHaml).
 *
 * There are two field that you, the developer, will need to change:
 * - hamlDirectory
 * - hamlCacheDirectory
 *
 * @package Slim
 * @author  Phillip Whelan <pwhelan@mixxx.org>
 */
class Layout extends \Slim\View
{
	protected $View;
	protected $Layout;
	
	public function __construct(\Slim\View $View, $Layout)
	{
		parent::__construct();
		$this->View = $View;
		$this->Layout = $Layout;
	}
	
	public function setTemplatesDirectory($dir)
	{
		parent::setTemplatesDirectory($dir);
		$this->View->setTemplatesDirectory($dir);
	}
	
	/**
	 * Renders Templates with layouts.
	 *
	 * @see View::render()
	 * @throws RuntimeException If MtHaml lib directory does not exist.
	 * @param string $template The template name specified in Slim::render()
	 * @return string
	 */	
	public function render($template, $data = null)
	{
		$this->View->replace($this->all());
		$this->View->set('yield', $this->View->render($template, $data));
		print $this->View->render($this->Layout, $data);
	}
}
