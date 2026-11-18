<?php defined('SYSPATH') OR die('No direct access allowed.');
/**
 * URL helper class.
 *
 * ###### Using the url helper:
 *
 *     // Using the url helper is simple:
 *     echo url::current();
 *
 *     // Output:
 *     welcome
 *
 * @package    Kohana
 * @author     Kohana Team
 * @copyright  (c) 2007-2010 Kohana Team
 * @license    http://kohanaphp.com/license
 */
class url_Core {

	/**
	 * This method returns the current URI route succeeding the
	 * index.php URI reference.
	 *
	 * ##### Example
	 *
	 *     // Assuming this url: http://localhost/kohana/index.php/welcome
	 *     echo url::current();
	 *
	 *     // Output:
	 *     welcome
	 *
	 * @param   boolean  $qs       Include the query string
	 * @param   boolean  $suffix   Include the suffix
	 * @return  varchar
	 */
	public static function current($qs = FALSE, $suffix = FALSE)
	{
		$uri = ($qs === TRUE) ? Router::$complete_uri : Router::$current_uri;

		return ($suffix === TRUE) ? $uri.Kohana::config('core.url_suffix') : $uri;
	}

	/**
	 * This method returns the base URI for your application, if the first
	 * function argument is omitted the index.php will not be included in the
	 * return value. If the second function argument is provided with
	 * a string it will be prefixed to the return value as the protocol.
	 *
	 * If protocol (and core.site_protocol) and core.site_domain are both empty,
	 * then this method will guess the protocol and the fully qualified domain.
	 *
	 * With a protocol and a core.site_domain without a host, the host comes
	 * from [url::host], so it must be in core.allowed_hosts.
	 *
	 * ##### Example
	 *
	 *     // This example assumes Kohana's default configuration
	 *     echo url::base();
	 *
	 *     // Output:
	 *     /kohana/
	 *
	 *     echo url::base(True, 'http');
	 *
	 *     // Output:
	 *     http://localhost/kohana/index.php/
	 *
	 * @param   boolean	  $index      Include the index page
	 * @param   mix	      $protocol   Non-default protocol
	 * @return  varchar
	 */
	public static function base($index = FALSE, $protocol = FALSE)
	{
		if ($protocol == FALSE)
		{
			// Use the default configured protocol
			$protocol = Kohana::config('core.site_protocol');
		}

		// Load the site domain
		$site_domain = (string) Kohana::config('core.site_domain', TRUE);

		if ($protocol == FALSE)
		{
			if ($site_domain === '' OR $site_domain[0] === '/')
			{
				// Use the configured site domain
				$base_url = $site_domain;
			}
			else
			{
				// Guess the protocol to provide full http://domain/path URL
				$base_url = ((empty($_SERVER['HTTPS']) OR $_SERVER['HTTPS'] === 'off') ? 'http' : 'https').'://'.$site_domain;
			}
		}
		else
		{
			if ($site_domain === '' OR $site_domain[0] === '/')
			{
				// Take the host from the request, if core.allowed_hosts allows it
				$base_url = $protocol.'://'.url::host().$site_domain;
			}
			else
			{
				// Use the configured site domain
				$base_url = $protocol.'://'.$site_domain;
			}
		}

		if ($index === TRUE AND $index = Kohana::config('core.index_page'))
		{
			// Append the index page
			$base_url = $base_url.$index;
		}

		// Force a slash on the end of the URL
		return rtrim($base_url, '/').'/';
	}

	/**
	 * Returns the host for full URLs when core.site_domain has no host. The
	 * Host header is used only if it is in core.allowed_hosts, because a
	 * client can send any Host header. For another host, the first entry of
	 * the list is used. The entry '*' allows any host.
	 *
	 * @throws  Kohana_Exception  if no allowed host is configured
	 * @return  string  host, with the port if the request has one
	 */
	public static function host()
	{
		$allowed = array_map('strtolower', array_map('strval', (array) Kohana::config('core.allowed_hosts')));

		$host = isset($_SERVER['HTTP_HOST']) ? strtolower((string) $_SERVER['HTTP_HOST']) : '';

		// Host name or IP literal, with an optional port
		if (preg_match('/^(?:[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?|\[[0-9a-f:.]+\])(?::[0-9]{1,5})?$/D', $host)
			AND (in_array($host, $allowed, TRUE) OR in_array('*', $allowed, TRUE)))
			return $host;

		foreach ($allowed as $entry)
		{
			if ($entry !== '*' AND $entry !== '')
				return $entry;
		}

		throw new Kohana_Exception('The request host is not in core.allowed_hosts. Add the host names of the site to core.allowed_hosts, or set core.site_domain with a host');
	}

	/**
	 * This method returns the absolute root URI of a given URI or, by
	 * default, of your application's URI route.
	 *
	 * The second function argument, if given a string, will be prefixed to the
	 * return value as the protocol.
	 *
	 * ##### Example
	 *
	 *     // Assuming this url: http://localhost/opensource/kohana/index.php/welcome
	 *     echo url::site();
	 *
	 *     // Output:
	 *     /kohana/index.php/
	 *
	 *     echo url::site('', 'http');
	 *
	 *     // Output:
	 *     http://localhost/kohana/index.php/
	 *
	 * @param   string	  $uri	     site URI to convert
	 * @param   mixed	  $protocol  non-default protocol
	 * @return  varchar
	 */
	public static function site($uri = '', $protocol = FALSE)
	{
		if ($path = trim(parse_url($uri, PHP_URL_PATH), '/'))
		{
			// Add path suffix
			$path .= Kohana::config('core.url_suffix');
		}

		if ($query = parse_url($uri, PHP_URL_QUERY))
		{
			// ?query=string
			$query = '?'.$query;
		}

		if ($fragment = parse_url($uri, PHP_URL_FRAGMENT))
		{
			// #fragment
			$fragment =  '#'.$fragment;
		}

		// Concat the URL
		return url::base(TRUE, $protocol).$path.$query.$fragment;
	}

	/**
	 * This method returns a qualified url path. Absolute
	 * filenames and relative filenames are allowed.
	 *
	 * The second function argument will include the index.php
	 * in the returned value.
	 *
	 * ##### Example
	 *
	 *     echo url::file('uploads/images/raw/narwhal.jpg');
	 *
	 *     // Output:
	 *     /kohana/uploads/images/raw/narwhal.jpg
	 *
	 * @param   string	$file   Filename
	 * @param   boolean	$index  Include the index page
	 * @return  string
	 */
	public static function file($file, $index = FALSE)
	{
		if (strpos($file, '://') === FALSE)
		{
			// Add the base URL to the filename
			$file = url::base($index).$file;
		}

		return $file;
	}

	/**
	 * This method merges an array of arguments with the current
	 * URI and query string to overload, instead of replace, the
	 * current query string.
	 *
	 * ##### Example
	 *
	 *     // Assuming this url: http://localhost/opensource/kohana/index.php/welcome
	 *     echo url::merge(array('unicorn', 'rambo_kitteh', 'narwhal', 'charlie'));
	 *
	 *     // Output:
	 *     welcome?0=unicorn&1=rambo_kitteh&2=narwhal&3=charlie
	 *
	 * @param   array   $arguments  Associative array of arguments
	 * @return  string
	 */
	public static function merge(array $arguments)
	{
		if ($_GET === $arguments)
		{
			$query = Router::$query_string;
		}
		elseif ($query = http_build_query(array_merge($_GET, $arguments)))
		{
			$query = '?'.$query;
		}

		// Return the current URI with the arguments merged into the query string
		return Router::$current_uri.$query;
	}

	/**
	 * This method transforms a given string into a URL safe
	 * string.
	 *
	 * The second function argument specifies the separator and the
	 * third function argument ensures transliteration of any special
	 * characters into ASCII.
	 *
	 * ##### Example
	 *
	 *     echo url::title('this is a cool phrase');
	 *
	 *     // Output:
	 *     this-is-a-cool-phrase
	 *
	 * @param   string   $title          Phrase to convert
	 * @param   string   $separatorword  Separator (- or _)
	 * @param   boolean	 $ascii_only     Transliterate to ASCII
	 * @return  string
	 */
	public static function title($title, $separator = '-', $ascii_only = FALSE)
	{
		$separator = ($separator === '-') ? '-' : '_';

		if ($ascii_only === TRUE)
		{
			// Replace accented characters by their unaccented equivalents
			$title = text::transliterate_to_ascii($title);

			// Remove all characters that are not the separator, a-z, 0-9, or whitespace
			$title = preg_replace('/[^'.$separator.'a-z0-9\s]+/', '', strtolower($title));
		}
		else
		{
			// Remove all characters that are not the separator, letters, numbers, or whitespace
			$title = preg_replace('/[^'.$separator.'\pL\pN\s]+/u', '', mb_strtolower($title));
		}

		// Replace all separator characters and whitespace by a single separator
		$title = preg_replace('/['.$separator.'\s]+/', $separator, $title);

		// Trim separators from the beginning and end
		return trim($title, $separator);
	}

	/**
	 * This method sends a page redirect header and runs the system.redirect
	 * Event. This method does safely exit after the header is sent.
	 *
	 * The second function argument is the status code to send during
	 * redirection; useful if you are redirecting because a page has
	 * been relocated to a different url permanently or temporarily.
	 *
	 * Accepted status codes are as follow:
	 *
	 * refresh
	 * 300
	 * 301
	 * 302
	 * 303
	 * 304
	 * 305
	 * 307
	 *
	 * @see http://en.wikipedia.org/wiki/List_of_HTTP_status_codes#3xx_Redirection
	 *
	 * ##### Example
	 *
	 *     // Assuming this base url: /kohana/
	 *     url::redirect('controller/method');
	 *
	 *     // Sends this Location header (a relative URL, so the Host header
	 *     // of the request has no effect):
	 *     /kohana/index.php/controller/method
	 *
	 * @param  mixed	string site URI or URL to redirect to, or array of strings if method is 300
	 * @param  varchar	HTTP status code
	 * @return void
	 */
	public static function redirect($uri = '', $status = '302')
	{
		if (Event::has_run('system.send_headers'))
		{
			return FALSE;
		}

		$codes = array
		(
			'refresh' => 'Refresh',
			'300' => 'Multiple Choices',
			'301' => 'Moved Permanently',
			'302' => 'Found',
			'303' => 'See Other',
			'304' => 'Not Modified',
			'305' => 'Use Proxy',
			'307' => 'Temporary Redirect'
		);

		// Validate the method and default to 302
		$status = isset($codes[$status]) ? (string) $status : '302';

		if ($status === '300')
		{
			$uri = (array) $uri;

			$output = '<ul>';
			foreach ($uri as $link)
			{
				$output .= '<li>'.html::anchor($link).'</li>';
			}
			$output .= '</ul>';

			// The first URI will be used for the Location header
			$uri = $uri[0];
		}
		else
		{
			$output = '<p>'.html::anchor($uri).'</p>';
		}

		// Run the redirect event
		Event::run('system.redirect', $uri);

		if ( ! preg_match('#^[a-z][a-z0-9+.-]*://#iD', $uri))
		{
			// A relative Location (RFC 7231) does not depend on the Host header
			$uri = url::site($uri);
		}

		if ($status === 'refresh')
		{
			header('Refresh: 0; url='.$uri);
		}
		else
		{
			header('HTTP/1.1 '.$status.' '.$codes[$status]);
			header('Location: '.$uri);
		}

		// We are about to exit, so run the send_headers event
		Event::run('system.send_headers');

		exit('<h1>'.$status.' - '.$codes[$status].'</h1>'.$output);
	}

	/**
	 * Redirects to a page of this site only. Use it for redirect targets from
	 * the request, for example a "return" parameter or [request::referrer].
	 * A target that [url::local_uri] refuses gives a redirect to the base URL.
	 *
	 * ##### Example
	 *
	 *     url::redirect_local($this->input->get('return'));
	 *
	 * @param   string  site URI, site path or URL of this site
	 * @param   string  HTTP status code, see [url::redirect]
	 * @return  void
	 */
	public static function redirect_local($uri = '', $status = '302')
	{
		$uri = url::local_uri($uri);

		return url::redirect(($uri === NULL) ? '' : $uri, $status);
	}

	/**
	 * Converts a redirect target to a site URI for [url::site], or returns
	 * NULL if the target can leave the site. Accepted targets:
	 *
	 * - a site URI, for example 'blog/post?page=2'
	 * - a path under the base path, for example '/kohana/index.php/blog'
	 * - an http or https URL under the base path, with the host of
	 *   core.site_domain or a host of core.allowed_hosts ('*' does not count)
	 *
	 * Control characters, backslashes and leading spaces make the target
	 * invalid, because browsers read "/\host" and "\t//host" as another host.
	 *
	 * @param   string  redirect target
	 * @return  string|NULL  site URI, or NULL
	 */
	public static function local_uri($uri)
	{
		if ( ! is_string($uri) OR preg_match('/[\x00-\x1f\x7f\\\\]|^ /', $uri))
			return NULL;

		if (strncmp($uri, '//', 2) === 0 OR ($parts = parse_url($uri)) === FALSE)
			return NULL;

		$domain = (string) Kohana::config('core.site_domain', TRUE);
		$domain_host = ($domain === '' OR $domain[0] === '/') ? '' : strtolower(strtok($domain, '/'));

		if (isset($parts['scheme']) OR isset($parts['host']) OR isset($parts['user']) OR isset($parts['port']))
		{
			if ( ! isset($parts['scheme'], $parts['host']) OR isset($parts['user']) OR ! in_array(strtolower($parts['scheme']), array('http', 'https'), TRUE))
				return NULL;

			$host = strtolower($parts['host']).(isset($parts['port']) ? ':'.$parts['port'] : '');
			$hosts = array_map('strtolower', array_map('strval', (array) Kohana::config('core.allowed_hosts')));

			if ($host !== $domain_host AND ( ! in_array($host, $hosts, TRUE)))
				return NULL;

			// Keep the path, the query and the fragment
			$uri = (isset($parts['path']) ? $parts['path'] : '/')
				.(isset($parts['query']) ? '?'.$parts['query'] : '')
				.(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
		}

		if ($uri !== '' AND $uri[0] === '/')
		{
			// A path must be under the base path
			$base = rtrim(($domain_host === '') ? $domain : (string) strstr($domain, '/'), '/').'/';
			$index = (string) Kohana::config('core.index_page');
			$path = NULL;

			foreach (($index === '') ? array($base) : array($base.$index.'/', $base) as $prefix)
			{
				if (strncmp($uri.'/', $prefix, strlen($prefix)) === 0)
				{
					$path = (string) substr($uri, strlen($prefix));
					break;
				}
			}

			$uri = $path;
		}

		// The site URI itself must be relative, for example not "http://host" or "/host"
		if ($uri === NULL OR ($uri !== '' AND ($uri[0] === '/' OR ($parts = parse_url($uri)) === FALSE OR isset($parts['scheme']) OR isset($parts['host']))))
			return NULL;

		return $uri;
	}

} // End url