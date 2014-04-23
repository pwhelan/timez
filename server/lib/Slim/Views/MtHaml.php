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
 * @author  Kacper Marcisz <http://kacpermarcisz.com/>
 */
class MtHaml extends \Slim\View
{
	/**
	 * @var string The path to the templates folder WITH the trailing slash
	 */
	public static $mthamlCacheDirectory = '../app/cache/mthaml/';


	/**
	 * Renders a template using Haml.php.
	 *
	 * @see View::render()
	 * @throws RuntimeException If MtHaml lib directory does not exist.
	 * @param string $template The template name specified in Slim::render()
	 * @return string
	 */	
	public function render($template, array $data = null)
	{
		$mthaml = new \MtHaml\Environment('php', array('enable_escaper' => false));
		$compiled_content = $mthaml->compileString(file_get_contents($this->templateDirectory . "/" . $template), $template);
		return $this->evaluate($compiled_content, $this->data);
	}
	
	/**
	 * Evalates the code return by MtHaml, inspired bu the way HamlPHP does evaluate it's code (https://github.com/sniemela/HamlPHP)
	 * @param string $content PHP code to evaluate
	 * @param array $contentVariables variables to be evaluated in said PHP code
	 */
	public function evaluate($content, array $contentVariables = null)
	{
		$tempFileName = tempnam("/tmp", "foo");
		$fp = fopen($tempFileName, "w");
		fwrite($fp, $content);
		
		ob_start();
		if ($contentVariables) {
			extract((array)$contentVariables);
		}
		
		require $tempFileName;
		$result = ob_get_clean();

		fclose($fp);
		unlink($tempFileName);
		return $result;
	}
}
