<?php defined('SYSPATH') OR die('No direct access allowed.');
/**
 * Input library.
 *
 * The system.routing event creates the instance, so the global input
 * cleaning and global xss filtering run on every request.
 *
 * ##### Loading the Library
 *
 *     // Get the singleton instance:
 *     $input = Input::instance();
 *
 * @package    Kohana
 * @author     Kohana Team
 * @copyright  (c) 2007-2010 Kohana Team
 * @license    http://kohanaphp.com/license
 */
class Input_Core {

	/**
	 * IP address of current user
	 * @var string $ip_address
	 */
	public $ip_address;

	/**
	 * Enable or disable automatic XSS cleaning
	 * @var boolean $use_xss_clean
	 */
	protected $use_xss_clean = FALSE;

	/**
	 * Are magic quotes enabled?
	 * @var boolean $magic_quotes_gpc
	 */
	protected $magic_quotes_gpc = FALSE;

	/**
	 * Input singleton
	 */
	protected static $instance;

	/**
	 * Retrieve a singleton instance of Input. This will always be the first
	 * created instance of this class.
	 *
	 * ##### Example
	 *
	 *    $input = Input::instance();
	 *
	 * @return  Input
	 */
	public static function instance()
	{
		if (Input::$instance === NULL)
		{
			// Create a new instance
			return new Input;
		}

		return Input::$instance;
	}

	/**
	 * Sanitizes global GET, POST and COOKIE data. Also takes care of
	 * magic_quotes and register_globals, if they have been enabled.
	 *
	 * @return  void
	 */
	public function __construct()
	{
		// Convert all global variables to Kohana charset
		$_GET    = Input::clean($_GET);
		$_POST   = Input::clean($_POST);
		$_COOKIE = Input::clean($_COOKIE);
		$_SERVER = Input::clean($_SERVER);

		if (Kohana::$server_api === 'cli')
		{
			// Convert command line arguments
			$_SERVER['argv'] = Input::clean($_SERVER['argv']);
		}

		// Use XSS clean?
		$this->use_xss_clean = (bool) Kohana::config('core.global_xss_filtering');

		if (Input::$instance === NULL)
		{
			// PHP 5.4 removed magic quotes, so $this->magic_quotes_gpc stays FALSE

			$_GET  = $this->clean_input_array($_GET);
			$_POST = $this->clean_input_array($_POST);

			// Keep the special attributes of RFC 2109 cookies
			$_COOKIE = $this->clean_input_array($_COOKIE, array('$Version', '$Path', '$Domain'));

			// Create a singleton
			Input::$instance = $this;

			Kohana_Log::add('debug', 'Global GET, POST and COOKIE data sanitized');
		}
	}

	/**
	 * Fetch an item from the $_GET array.
	 *
	 * ##### Example
	 *
	 *    // URL is http://www.example.com/index.php?articleId=123&file=text.txt
	 *
	 *    echo Kohana::debug($input->get());
	 *    echo Kohana::debug($input->get('file'));
	 *
	 *    // Output:
	 *    Array
	 *    (
	 *        [articleId] => 123
	 *        [file] => text.txt
	 *    )
	 *
	 *    text.txt
	 *
	 *    // You can also pass a default value if the key doesn't exist and manually XSS clean the request:
	 *    $input->get('file', 'default_value', TRUE);
	 *
	 * @param   string   $key         Key to find
	 * @param   mixed    $default     Default value
	 * @param   boolean  $xss_clean   XSS clean the value
	 * @return  mixed
	 */
	public function get($key = array(), $default = NULL, $xss_clean = FALSE)
	{
		return $this->search_array($_GET, $key, $default, $xss_clean);
	}

	/**
	 * Fetch an item from the $_POST array.
	 *
	 * ##### Example
	 *
	 *    // POST variables are articleId=123 and file=text.txt
	 *
	 *    echo Kohana::debug($input->post());
	 *    echo Kohana::debug($input->post('file'));
	 *
	 *    // Output:
	 *    Array
	 *    (
	 *        [articleId] => 123
	 *        [file] => text.txt
	 *    )
	 *
	 *    text.txt
	 *
	 *    // You can also pass a default value if the key doesn't exist and manually XSS clean the request:
	 *    $input->post('file', 'default_value', TRUE);
	 *
	 * @param   string   $key         Key to find
	 * @param   mixed    $default     Default value
	 * @param   boolean  $xss_clean   XSS clean the value
	 * @return  mixed
	 */
	public function post($key = array(), $default = NULL, $xss_clean = FALSE)
	{
		return $this->search_array($_POST, $key, $default, $xss_clean);
	}

	/**
	 * Fetch an item from the cookie::get() ($_COOKIE won't work with signed
	 * cookies.)
	 *
	 * ##### Example
	 *
	 *    // COOKIE name is "username" and the contents of this cookie is "aart-jan".
	 *    // Note that print statements are for documentation purpose only
	 *
	 *    echo Kohana::debug($input->cookie());
	 *    echo Kohana::debug($input->cookie('username'));
	 *
	 *    // Output:
	 *    Array
	 *    (
	 *        [username] => aart-jan
	 *    )
	 *
	 *    aart-jan
	 *
	 *    // You can also pass a default value if the key doesn't exist and manually XSS clean the request:
	 *    $input->cookie('username', 'default_value', TRUE);
	 *
	 * @param   string   $key         Key to find
	 * @param   mixed    $default     Default value
	 * @param   boolean  $xss_clean   XSS clean the value
	 * @return  mixed
	 */
	public function cookie($key = array(), $default = NULL, $xss_clean = FALSE)
	{
		return $this->search_array(cookie::get(), $key, $default, $xss_clean);
	}

	/**
	 * Fetch an item from the $_SERVER array.
	 *
	 * ##### Example
	 *
	 *    echo Kohana::debug($input->server('HTTP_HOST'));
	 *
	 *    // Output:
	 *    localhost
	 *
	 *    // You can also pass a default value if the key doesn't exist and manually XSS clean the request:
	 *    $input->server('HTTP_HOST', 'default_value', TRUE);
	 *
	 * @param   string   $key         Key to find
	 * @param   mixed    $default     Default value
	 * @param   boolean  $xss_clean   XSS clean the value
	 * @return  mixed
	 */
	public function server($key = array(), $default = NULL, $xss_clean = FALSE)
	{
		return $this->search_array($_SERVER, $key, $default, $xss_clean);
	}

	/**
	 * Fetch an item from a global array.
	 *
	 * @param   array    $array       Array to search
	 * @param   string   $key         Key to find
	 * @param   mixed    $default     Default value
	 * @param   boolean  $xss_clean   XSS clean the value
	 * @return  mixed
	 */
	protected function search_array($array, $key, $default = NULL, $xss_clean = FALSE)
	{
		if ($key === array())
			return $array;

		if ( ! isset($array[$key]))
			return $default;

		// Get the value
		$value = $array[$key];

		if ($this->use_xss_clean === FALSE AND $xss_clean === TRUE)
		{
			// XSS clean the value
			$value = $this->xss_clean($value);
		}

		return $value;
	}

	/**
	 * Fetch the IP Address.
	 *
	 * Returns REMOTE_ADDR. When REMOTE_ADDR is in the input.trusted_proxies
	 * config, it returns the right-most X-Forwarded-For (or Client-IP) hop
	 * that is not a trusted proxy. Returns '0.0.0.0' when there is no valid
	 * address.
	 *
	 * ##### Example
	 *
	 *    echo $input->ip_address();
	 *
	 *    // Output:
	 *    127.0.0.1
	 *
	 * @return string
	 */
	public function ip_address()
	{
		if ($this->ip_address !== NULL)
			return $this->ip_address;

		$ip = isset($_SERVER['REMOTE_ADDR']) ? trim($_SERVER['REMOTE_ADDR']) : '';

		if ( ! filter_var($ip, FILTER_VALIDATE_IP))
			return $this->ip_address = '0.0.0.0';

		$proxies = (array) Kohana::config('input.trusted_proxies');

		if ($proxies AND Input::ip_in_list($ip, $proxies))
		{
			if ( ! empty($_SERVER['HTTP_X_FORWARDED_FOR']))
			{
				$hops = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
			}
			elseif ( ! empty($_SERVER['HTTP_CLIENT_IP']))
			{
				$hops = array($_SERVER['HTTP_CLIENT_IP']);
			}
			else
			{
				$hops = array();
			}

			// Only a trusted hop vouches for the hop on its left
			foreach (array_reverse($hops) as $hop)
			{
				$hop = trim($hop);

				if ( ! filter_var($hop, FILTER_VALIDATE_IP))
					break;

				$ip = $hop;

				if ( ! Input::ip_in_list($hop, $proxies))
					break;
			}
		}

		return $this->ip_address = $ip;
	}

	/**
	 * Checks if an IP address is in one of a list of addresses or CIDR ranges.
	 *
	 * @param   string  $ip      IP address
	 * @param   array   $ranges  IP addresses or CIDR ranges
	 * @return  boolean
	 */
	public static function ip_in_list($ip, array $ranges)
	{
		foreach ($ranges as $range)
		{
			if (Input::ip_in_range($ip, $range))
				return TRUE;
		}

		return FALSE;
	}

	/**
	 * Checks if an IP address is in a CIDR range ('10.0.0.0/8',
	 * '2001:db8::/32') or is equal to a single address. IPv4 and IPv6 do not
	 * match each other.
	 *
	 * @param   string  $ip     IP address
	 * @param   string  $range  IP address or CIDR range
	 * @return  boolean
	 */
	public static function ip_in_range($ip, $range)
	{
		$parts  = explode('/', trim((string) $range), 2);
		$subnet = $parts[0];

		if ( ! filter_var($ip, FILTER_VALIDATE_IP) OR ! filter_var($subnet, FILTER_VALIDATE_IP))
			return FALSE;

		$ip     = inet_pton($ip);
		$subnet = inet_pton($subnet);

		if (strlen($ip) !== strlen($subnet))
			return FALSE;

		$max = strlen($ip) * 8;

		if (isset($parts[1]))
		{
			if ( ! ctype_digit($parts[1]) OR (int) $parts[1] > $max)
				return FALSE;

			$bits = (int) $parts[1];
		}
		else
		{
			$bits = $max;
		}

		$bytes = intdiv($bits, 8);

		if (substr($ip, 0, $bytes) !== substr($subnet, 0, $bytes))
			return FALSE;

		if ($bits % 8 === 0)
			return TRUE;

		$mask = chr((0xFF << (8 - $bits % 8)) & 0xFF);

		return ($ip[$bytes] & $mask) === ($subnet[$bytes] & $mask);
	}

	/**
	 * Clean cross site scripting exploits from string.
	 * HTMLPurifier may be used if installed, otherwise defaults to built in method.
	 * Note - This function should only be used to deal with data upon submission.
	 * It's not something that should be used for general runtime processing
	 * since it requires a fair amount of processing overhead.
	 *
	 * [!!] The default tool is a blacklist filter and cannot be complete.
	 * For HTML from users, use the 'htmlpurifier' tool (HTML Purifier is a
	 * whitelist filter) or escape the output with html::chars().
	 *
	 * ##### Example
	 *
	 *    // Clean the input variable with the default tool
	 *    echo $input->xss_clean($suspect_input);
	 *
	 *    // Clean the input variable with the specified tool
	 *    echo $input->xss_clean($suspect_input, 'htmlpurifier');
	 *
	 * @param   string  $data   Data to clean
	 * @param   string  $tool   xss_clean method to use ('htmlpurifier' or defaults to built-in method)
	 * @return  string
	 */
	public function xss_clean($data, $tool = NULL)
	{
		if ($tool === NULL)
		{
			// Use the default tool
			$tool = Kohana::config('core.global_xss_filtering');
		}

		if (is_array($data))
		{
			foreach ($data as $key => $val)
			{
				$data[$key] = $this->xss_clean($val, $tool);
			}

			return $data;
		}

		// Do not clean empty strings
		if (trim($data) === '')
			return $data;

		if (is_bool($tool))
		{
			$tool = 'default';
		}
		elseif ( ! method_exists($this, 'xss_filter_'.$tool))
		{
			Kohana_Log::add('error', 'Unable to use Input::xss_filter_'.$tool.'(), no such method exists');
			$tool = 'default';
		}

		$method = 'xss_filter_'.$tool;

		return $this->$method($data);
	}

	/**
	 * Default built-in cross site scripting filter.
	 *
	 * @param   string  $data  Data to clean
	 * @return  string
	 */
	protected function xss_filter_default($data)
	{
		// http://svn.bitflux.ch/repos/public/popoon/trunk/classes/externalinput.php
		// +----------------------------------------------------------------------+
		// | Copyright (c) 2001-2006 Bitflux GmbH                                 |
		// +----------------------------------------------------------------------+
		// | Licensed under the Apache License, Version 2.0 (the "License");      |
		// | you may not use this file except in compliance with the License.     |
		// | You may obtain a copy of the License at                              |
		// | http://www.apache.org/licenses/LICENSE-2.0                           |
		// | Unless required by applicable law or agreed to in writing, software  |
		// | distributed under the License is distributed on an "AS IS" BASIS,    |
		// | WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or      |
		// | implied. See the License for the specific language governing         |
		// | permissions and limitations under the License.                       |
		// +----------------------------------------------------------------------+
		// | Author: Christian Stocker <chregu@bitflux.ch>                        |
		// +----------------------------------------------------------------------+
		//
		// Kohana Modifications:
		// * Changed double quotes to single quotes, changed indenting and spacing
		// * Removed magic_quotes stuff
		// * Increased regex readability:
		//   * Used delimeters that aren't found in the pattern
		//   * Removed all unneeded escapes
		//   * Deleted U modifiers and swapped greediness where needed
		// * Increased regex speed:
		//   * Made capturing parentheses non-capturing where possible
		//   * Removed parentheses where possible
		//   * Split up alternation alternatives
		//   * Made some quantifiers possessive

		// Fix &entity\n; (the HTML5 table also has &LT; &GT; &AMP;)
		$data = preg_replace('/&(amp|lt|gt);/i', '&amp;$1;', $data);
		$data = preg_replace('/(&#*\w+)[\x00-\x20]+;/u', '$1;', $data);
		$data = preg_replace('/(&#x*[0-9A-F]+);*/iu', '$1;', $data);
		$data = html_entity_decode($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');

		// Remove URL and style attributes that use a script or data scheme
		$data = preg_replace_callback('#(?<=[\x00-\x20"\'/])(?:href|src|srcset|lowsrc|dynsrc|action|formaction|background|poster|style|xlink:href)[\x00-\x20]*=[\x00-\x20]*(?:"[^"]*+"?|\'[^\']*+\'?|[^\x00-\x20>]*+)#iu', array($this, 'xss_filter_attribute'), $data);

		// Remove any attribute starting with "on" or xmlns
		$data = preg_replace('#(?:on[a-z]+|xmlns)\s*=\s*[\'"\x00-\x20]?[^\'>"]*[\'"\x00-\x20]?\s?#iu', '', $data);

		// Remove javascript: and vbscript: protocols
		$data = preg_replace('#([a-z]*)[\x00-\x20]*=[\x00-\x20]*([`\'"]*)[\x00-\x20]*j[\x00-\x20]*a[\x00-\x20]*v[\x00-\x20]*a[\x00-\x20]*s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:#iu', '$1=$2nojavascript...', $data);
		$data = preg_replace('#([a-z]*)[\x00-\x20]*=([\'"]*)[\x00-\x20]*v[\x00-\x20]*b[\x00-\x20]*s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:#iu', '$1=$2novbscript...', $data);
		$data = preg_replace('#([a-z]*)[\x00-\x20]*=([\'"]*)[\x00-\x20]*-moz-binding[\x00-\x20]*:#u', '$1=$2nomozbinding...', $data);

		// Only works in IE: <span style="width: expression(alert('Ping!'));"></span>
		$data = preg_replace('#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?expression[\x00-\x20]*\([^>]*+>#is', '$1>', $data);
		$data = preg_replace('#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?behaviour[\x00-\x20]*\([^>]*+>#is', '$1>', $data);
		$data = preg_replace('#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:*[^>]*+>#ius', '$1>', $data);

		// Remove namespaced elements (we do not need them)
		$data = preg_replace('#</*\w+:\w[^>]*+>#i', '', $data);

		do
		{
			// Remove really unwanted tags
			$old_data = $data;
			$data = preg_replace('#<[\x00-\x20]*/*[\x00-\x20]*+(?:applet|b(?:ase|gsound|link)|embed|frame(?:set)?|i(?:frame|layer)|l(?:ayer|ink)|meta|object|s(?:cript|tyle)|title|xml)[^>]*+#i', '', $data);
		}
		while ($old_data !== $data);

		return $data;
	}

	/**
	 * Callback for xss_filter_default(): returns an empty string for an
	 * attribute whose value uses the javascript:, vbscript: or data: scheme.
	 * The value is decoded until it is stable, because the filter output
	 * gets one more decode in the browser.
	 *
	 * @param   array   $matches  the attribute in $matches[0]
	 * @return  string
	 */
	protected function xss_filter_attribute(array $matches)
	{
		$equals = strpos($matches[0], '=');
		$name   = strtolower(rtrim(substr($matches[0], 0, $equals)));
		$value  = substr($matches[0], $equals + 1);

		for ($i = 0; $i < 5; $i++)
		{
			$decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

			if ($decoded === $value)
				break;

			$value = $decoded;
		}

		// Browsers ignore control characters and spaces in a scheme
		$value = strtolower(preg_replace('/[\x00-\x20`\'"]+/', '', $value));

		// A style or a srcset can hold a URL at any position
		$anchor = ($name === 'style' OR $name === 'srcset') ? '' : '^';

		if (preg_match('/'.$anchor.'(?:javascript|vbscript|data):/', $value))
			return '';

		return $matches[0];
	}

	/**
	 * HTMLPurifier cross site scripting filter. This version assumes the
	 * existence of the "Standalone Distribution" htmlpurifier library, and is set to not tidy
	 * input.
	 *
	 * @param   string  $data  Data to clean
	 * @return  string
	 */
	protected function xss_filter_htmlpurifier($data)
	{
		/**
		 * @todo License should go here, http://htmlpurifier.org/
		 */
		if ( ! class_exists('HTMLPurifier_Config', FALSE))
		{
			// Load HTMLPurifier
			require Kohana::find_file('vendor', 'htmlpurifier/HTMLPurifier.standalone', TRUE);
		}

		// Set configuration
		$config = HTMLPurifier_Config::createDefault();
		$config->set('HTML.TidyLevel', 'none'); // Only XSS cleaning now

		$cache = Kohana::config('html_purifier.cache');

		if ($cache AND is_string($cache))
		{
			$config->set('Cache.SerializerPath', $cache);
		}

		// Run HTMLPurifier
		$data = HTMLPurifier::instance($config)->purify($data);

		return $data;
	}

	/**
	 * Cleans the keys and the values of a global array. Removes the items
	 * with a disallowed key.
	 *
	 * @param   mixed  $array  global array
	 * @param   array  $keep   keys to keep without cleaning
	 * @return  array
	 */
	protected function clean_input_array($array, array $keep = array())
	{
		if ( ! is_array($array))
			return array();

		$clean = array();

		foreach ($array as $key => $val)
		{
			if (in_array($key, $keep, TRUE))
			{
				$clean[$key] = $val;
			}
			elseif (($key = $this->clean_input_keys($key)) !== FALSE)
			{
				$clean[$key] = $this->clean_input_data($val);
			}
		}

		return $clean;
	}

	/**
	 * This is a helper method. It enforces W3C specifications for allowed
	 * key name strings, to prevent malicious exploitation.
	 *
	 * A disallowed key is logged and returns FALSE. The caller must drop it:
	 * a foreign cookie name must not stop the request.
	 *
	 * ##### Example
	 *
	 *    $clean = $input->clean_input_keys($input);
	 *
	 * @param   string  $str  String to clean
	 * @return  string|FALSE  the key, or FALSE when it is not allowed
	 */
	public function clean_input_keys($str)
	{
		if ( ! preg_match('#^[\pL0-9:_.-]++$#uD', (string) $str))
		{
			Kohana_Log::add('alert', 'Input: dropped a global data key with disallowed characters: '.preg_replace('/[^\x20-\x7E]/', '?', substr($str, 0, 64)));

			return FALSE;
		}

		return $str;
	}

	/**
	 * This is a helper method. It escapes data and forces all newline
	 * characters to "\n".
	 *
	 * ##### Example
	 *
	 *    $clean = $input->clean_input_data($input);
	 *
	 * @param   mixed  $str  String to clean
	 * @return  string
	 */
	public function clean_input_data($str)
	{
		if (is_array($str))
		{
			$new_array = array();
			foreach ($str as $key => $val)
			{
				if (($key = $this->clean_input_keys($key)) !== FALSE)
				{
					// Recursion!
					$new_array[$key] = $this->clean_input_data($val);
				}
			}
			return $new_array;
		}

		if ($this->magic_quotes_gpc === TRUE)
		{
			// Remove annoying magic quotes
			$str = stripslashes($str);
		}

		if ($this->use_xss_clean === TRUE)
		{
			$str = $this->xss_clean($str);
		}

		if (strpos($str, "\r") !== FALSE)
		{
			// Standardize newlines
			$str = str_replace(array("\r\n", "\r"), "\n", $str);
		}

		return $str;
	}

	/**
	 * Recursively cleans arrays, objects, and strings. Removes ASCII control
	 * codes and converts to UTF-8 while silently discarding incompatible
	 * UTF-8 characters.
	 *
	 * ##### Example
	 *
	 *    // Takes a string, array, or object
	 *    $clean = $input->clean($_POST);
	 *
	 * @param   string  $str  String to clean
	 * @return  string
	 */
	public static function clean($str)
	{
		if (is_array($str) OR is_object($str))
		{
			foreach ($str as $key => $val)
			{
				$clean_key = Input::clean($key);

				if ($clean_key !== $key)
				{
					// Remove the uncleaned key
					unset($str[$key]);
				}

				// Recursion!
				$str[$clean_key] = Input::clean($val);
			}
		}
		elseif (is_string($str) AND $str !== '')
		{
			// Remove control characters
			$str = text::strip_ascii_ctrl($str);

			if ( ! text::is_ascii($str))
			{
				// Disable notices
				$ER = error_reporting(~E_NOTICE);

				// iconv is expensive, so it is only used when needed
				$str = iconv(Kohana::CHARSET, Kohana::CHARSET.'//IGNORE', $str);

				// Turn notices back on
				error_reporting($ER);
			}
		}

		return $str;
	}

} // End Input Class
