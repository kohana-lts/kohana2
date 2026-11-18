<?php defined('SYSPATH') OR die('No direct access allowed.');
/**
 * Provides methods for working with signed cookies.
 * Because this helper creates signed cookies, so you __cannot__
 * access the cookies directly using `$_COOKIE`. You must use [cooke::get]
 * and [cookie::set] to read and write signed cookies.
 *
 * Default settings for cookies are specified in the [cookie config](config/cookie) file.
 * You may override these settings by using the optional [cookie::set] parameters.
 *
 * @package    Kohana
 * @author     Kohana Team
 * @copyright  (c) 2007-2010 Kohana Team
 * @license    http://kohanaphp.com/license
 */
class cookie_Core {

	// Length of the signature from cookie::salt()
	const SIGNATURE_LENGTH = 64;

	// The salt that Kohana 2.4 shipped. It is public, so cookie::check_salt() refuses it.
	const PUBLIC_SALT = 'K0hAN4 15 Th3 B357';

	/**
	 * Defines a new cookie to be sent along with the http request.
	 * For more information about creating cookies please see the
	 * php docs on [setcookie()](http://us3.php.net/setcookie).
	 *
	 * For more information about this function's parameters see the
	 * [cookie config](config/cookie) docs.
	 *
	 * [!!] Note: Cookies set with this method are signed to prevent tampering. You __must__ use [cookie::get] to read these cookies
	 *
	 * ##### Example
	 *
	 *     cookie::set('example', 'Hello world!');
	 *
	 *     // Override default config values
	 *     $cookie_params = array(
	 *         'name'   => 'Very_Important_Cookie',
	 *         'value'  => 'Choclate Flavoured Mint Delight',
	 *         'expire' => '86500',
	 *         'domain' => '.example.com',
	 *         'path'   => '/'
	 *         );
	 *     cookie::set($cookie_params);
	 *
	 * @param   string   $name       Cookie name or array of config options
	 * @param   string   $value      Cookie value
	 * @param   integer  $expire     Number of seconds before the cookie expires
	 * @param   string   $path       URL path to allow
	 * @param   string   $domain     URL domain to allow
	 * @param   boolean  $secure     HTTPS only
	 * @param   boolean  $httponly   HTTP only
	 * @param   string   $samesite   SameSite attribute: 'Lax', 'Strict', 'None' or '' for none
	 * @return  boolean
	 * @throws  Kohana_Exception  if cookie.salt is not set
	 */
	public static function set($name, $value = NULL, $expire = NULL, $path = NULL, $domain = NULL, $secure = NULL, $httponly = NULL, $samesite = NULL)
	{
		cookie::check_salt();

		if (headers_sent())
			return FALSE;

		if (is_array($name))
		{
			// Import the options of the array
			$options = $name;
			$name = NULL;

			foreach (array('name', 'value', 'expire', 'path', 'domain', 'secure', 'httponly', 'samesite') as $item)
			{
				if (array_key_exists($item, $options))
				{
					$$item = $options[$item];
				}
			}
		}

		// Fetch default options
		$config = Kohana::config('cookie');

		foreach (array('value', 'expire', 'domain', 'path', 'secure', 'httponly', 'samesite') as $item)
		{
			if ($$item === NULL AND isset($config[$item]))
			{
				$$item = $config[$item];
			}
		}

		if ($expire != 0)
		{
			 // The expiration is expected to be a UNIX timestamp
			$expire += time();
		}

		$value = cookie::salt($name, $value).'~'.$value;

		return cookie::send($name, $value, $expire, $path, $domain, $secure, $httponly, $samesite);
	}

	/**
	 * Fetch a cookie value from a signed cookie or an array of cookies if no cookie name is
	 * specified.
	 *
	 * [!!] Note: This function only works with signed cookies set using [cookie::set].
	 *
	 * ##### Example
	 *
	 *     echo Kohana::debug(cookie::get('example'));
	 *
	 *     // Output (using our example above):
	 *     (string) Hello world!
	 *
	 *     echo Kohana::debug(cookie::get('nonexistent', 'default value'));
	 *
	 *     // Output:
	 *     (string) default value
	 *
	 * @param   string   $name       Cookie name
	 * @param   mixed    $default    Default value
	 * @param   boolean  $xss_clean  Use XSS cleaning on the value
	 * @return  mixed Cookie value or array of cookies
	 */
	public static function get($name = NULL, $default = NULL, $xss_clean = FALSE)
	{
		// Return an array of all the cookies if we don't have a name
		if ($name === NULL)
		{
			$cookies = array();

			foreach($_COOKIE AS $key => $value)
			{
				$cookies[$key] = cookie::get($key, $default, $xss_clean);
			}
			return $cookies;
		}

		if ( ! isset($_COOKIE[$name]))
		{
			return $default;
		}

		// Get the cookie value
		$cookie = $_COOKIE[$name];

		if (is_string($cookie) AND isset($cookie[cookie::SIGNATURE_LENGTH]) AND $cookie[cookie::SIGNATURE_LENGTH] === '~')
		{
			// Separate the signature and the value
			$hash = substr($cookie, 0, cookie::SIGNATURE_LENGTH);
			$value = (string) substr($cookie, cookie::SIGNATURE_LENGTH + 1);

			if (hash_equals(cookie::salt($name, $value), $hash))
			{
				if ($xss_clean === TRUE AND Kohana::config('core.global_xss_filtering') === FALSE)
				{
					return Input::instance()->xss_clean($value);
				}
				// Cookie signature is valid
				return $value;
			}

			// The cookie signature is invalid, delete it
			cookie::delete($name);
		}

		return $default;
	}

	/**
	 * Nullify and unset a cookie. This method deletes a cookie by setting its
	 * value to an empty string and a expiration of 24 hours ago.
	 *
	 * ##### Example
	 *
	 *     cookie::delete('example');
	 *     echo Kohana::debug(cookie::get('example'));
	 *
	 *     // Output:
	 *     (NULL)
	 *
	 * @param   string   $name    Cookie name
	 * @param   string   $path    URL path
	 * @param   string   $domain  URL domain
	 * @return  boolean
	 */
	public static function delete($name, $path = NULL, $domain = NULL)
	{
		// Delete the cookie from globals
		unset($_COOKIE[$name]);

		if (headers_sent())
			return FALSE;

		$config = Kohana::config('cookie');

		($path === NULL) and $path = $config['path'];
		($domain === NULL) and $domain = $config['domain'];

		// An empty value needs no signature, so this works without a salt
		return cookie::send($name, '', time() - 86400, $path, $domain, ! empty($config['secure']), ! empty($config['httponly']), isset($config['samesite']) ? $config['samesite'] : '');
	}

	/**
	 * Sends a Set-Cookie header.
	 *
	 * @throws  Kohana_Exception  for an unknown SameSite value
	 * @return  boolean
	 */
	protected static function send($name, $value, $expire, $path, $domain, $secure, $httponly, $samesite)
	{
		$options = array(
			'expires'  => (int) $expire,
			'path'     => (string) $path,
			'domain'   => (string) $domain,
			'secure'   => (bool) $secure,
			'httponly' => (bool) $httponly,
		);

		if ((string) $samesite !== '')
		{
			$samesite = ucfirst(strtolower((string) $samesite));

			if ( ! in_array($samesite, array('Lax', 'Strict', 'None'), TRUE))
				throw new Kohana_Exception('The cookie SameSite value must be Lax, Strict, None or empty');

			$options['samesite'] = $samesite;
		}

		return setcookie($name, (string) $value, $options);
	}

	/**
	 * Throws an exception if cookie.salt is empty or the public value that
	 * Kohana 2.4 shipped. Anybody can sign cookies with a known salt.
	 *
	 * @throws  Kohana_Exception
	 * @return  void
	 */
	public static function check_salt()
	{
		$salt = (string) Kohana::config('cookie.salt');

		if ($salt === '' OR $salt === cookie::PUBLIC_SALT)
			throw new Kohana_Exception('Set cookie.salt in application/config/cookie.php to a long random secret before you use signed cookies or sessions');
	}

	/**
	 * Returns the signature of a cookie: HMAC-SHA256 with cookie.salt as the
	 * key. The data has length prefixes, so a different split of the same
	 * name and value bytes gives a different signature.
	 *
	 * The User-Agent is part of the data only if cookie.user_agent is TRUE.
	 *
	 * @param	string   $name    Name of cookie
	 * @param	string   $value   Value of cookie
	 * @return	string   64 hexadecimal characters
	 */
	public static function salt($name, $value)
	{
		cookie::check_salt();

		$agent = '';

		if (Kohana::config('cookie.user_agent'))
		{
			$agent = isset($_SERVER['HTTP_USER_AGENT']) ? strtolower($_SERVER['HTTP_USER_AGENT']) : 'unknown';
		}

		$name = (string) $name;
		$value = (string) $value;

		$data = strlen($name).':'.$name.'|'.strlen($agent).':'.$agent.'|'.$value;

		return hash_hmac('sha256', $data, (string) Kohana::config('cookie.salt'));
	}

	/**
	 * This is a static helper class, no instance can be created.
	 */
	final private function __construct() {}

} // End cookie