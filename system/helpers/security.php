<?php defined('SYSPATH') OR die('No direct access allowed.');
/**
 * The security helper provides convenience methods for common
 * security practices; such as, XSS cleaning.
 *
 * #### Using the security helper
 *
 *     // Lets sanitize a string with the default method
 *     echo Kohana::debug(security::xss_clean("'';!--\"<XSS>=&{()}"));
 *     
 *     // Output:
 *     (string) &#039;&#039;;!--&quot;&lt;XSS&gt;=&amp;{()}
 *
 * @package    Kohana
 * @author     Kohana Team
 * @copyright  (c) 2007-2010 Kohana Team
 * @license    http://kohanaphp.com/license
 */
class security_Core {

	/**
	 * This method sanitizes a string to be XSS safe.
	 *
	 * The second function argument is a string denoting which tool
	 * you wish to use (possibly *htmlpurifier*), the default is
	 * Kohana's built in XSS sanitizing method found in the Input
	 * library.
	 *
	 * @link [class:input]
	 *
	 * ###### Example
	 *
	 *     // Sanitize using *htmlpurifier*
	 *     echo Kohana::debug(security::xss_clean("'';!--\"<XSS>=&{()}", 'htmlpurifier'));
	 *     
	 *     // Output:
	 *     (string) &#039;&#039;;!--&quot;&lt;XSS&gt;=&amp;{()}
	 *
	 * @param   string  $str  String to sanitize
	 * @param   string  $tool Sanitization method to use, default is Kohana's xss_clean method
	 * @return  string
	 */
	public static function xss_clean($str, $tool = NULL)
	{
		return Input::instance()->xss_clean($str, $tool);
	}

	/**
	 * Replaces each `<img>` and `<image>` tag with its escaped `src` value.
	 * This is not an HTML sanitizer: other tags stay. Use the 'htmlpurifier'
	 * tool of xss_clean() for untrusted HTML.
	 *
	 * The old regular expression needed white space after "<img", so
	 * "<img/src=x onerror=...>" passed (CVE-2016-10510).
	 *
	 * ###### Example
	 *
	 *     echo security::strip_image_tags('a <img src="kitteh.png" alt="Kitteh" /> b');
	 *
	 *     // Output:
	 *     a kitteh.png b
	 *
	 * @param   string  $str String to sanitize
	 * @return  string
	 */
	public static function strip_image_tags($str)
	{
		$str = (string) $str;

		// Repeat until stable, because a removal can join the parts of a new tag
		do
		{
			$result = preg_replace_callback('#<(?:img|image)\b([^>]*)>?#i', function ($tag)
			{
				if (preg_match('#(?:^|[\s/])src\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'>]+))#i', $tag[1], $src))
				{
					// The source never contains "<" after escaping
					return html::chars(implode('', array_slice($src, 1)), FALSE);
				}

				return '';
			}, $str, -1, $count);

			if ($result === NULL)
			{
				// Fail closed on a PCRE error
				return '';
			}

			$str = $result;
		}
		while ($count > 0);

		return $str;
	}

} // End security