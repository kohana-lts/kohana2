<?php
/**
 * Base class for tests that need real HTTP headers (cookies, sessions,
 * redirects). Each request() runs tests/data/request_cgi.php under php-cgi,
 * because the CLI SAPI does not keep headers and PHPUnit output blocks them.
 */
abstract class Kohana_CgiTestCase extends Kohana_TestCase {

	const SALT = 'test-salt-6mQ2vY8pLr4tKx9wZb3nHc7jDf5sGa1e';
	const KEY  = 'test-key-Nw3Rk8Lq2Zv6Tp9Yb4Hm7Jc1Xs5Df0Gu';

	protected static $cgi;

	// Session files of this test
	protected $save_path;

	protected function setUp(): void
	{
		parent::setUp();

		if (Kohana_CgiTestCase::$cgi === NULL)
		{
			Kohana_CgiTestCase::$cgi = (string) getenv('KOHANA_TEST_PHP_CGI');

			if (Kohana_CgiTestCase::$cgi === '')
			{
				Kohana_CgiTestCase::$cgi = (string) exec('command -v php-cgi 2>/dev/null');
			}
		}

		if (Kohana_CgiTestCase::$cgi === '')
			$this->markTestSkipped('php-cgi is not available (set KOHANA_TEST_PHP_CGI)');

		$this->save_path = sys_get_temp_dir().'/kohana-test-sessions-'.bin2hex(random_bytes(6));
		mkdir($this->save_path, 0700);
	}

	protected function tearDown(): void
	{
		if ($this->save_path !== NULL AND is_dir($this->save_path))
		{
			array_map('unlink', glob($this->save_path.'/*') ?: array());
			rmdir($this->save_path);
		}

		parent::tearDown();
	}

	/**
	 * Default configuration of each request.
	 *
	 * @return  array
	 */
	protected function default_config()
	{
		return array(
			'cookie.salt' => Kohana_CgiTestCase::SALT,
			'encryption.default.key' => Kohana_CgiTestCase::KEY,
			'session.driver' => 'native',
		);
	}

	/**
	 * Runs one request.
	 *
	 * @param   array  steps, see tests/data/request_cgi.php
	 * @param   array  cookies name => raw value
	 * @param   array  config overrides
	 * @param   array  server variables, for example HTTP_HOST
	 * @return  array  results, error, notices, session_id, session, headers, cookies
	 */
	protected function request(array $steps, array $cookies = array(), array $config = array(), array $server = array())
	{
		$scenario = array(
			'config' => $config + $this->default_config(),
			'ini' => array('session.save_path' => $this->save_path),
			'steps' => $steps,
		);

		$pairs = array();
		foreach ($cookies as $name => $value)
		{
			$pairs[] = $name.'='.rawurlencode($value);
		}

		$env = $server + array(
			'KOHANA_TEST_SCENARIO' => json_encode($scenario),
			'HTTP_COOKIE' => implode('; ', $pairs),
			'HTTP_USER_AGENT' => 'KohanaTest/1.0',
			'HTTP_HOST' => 'www.example.com',
			'REMOTE_ADDR' => '192.0.2.10',
			'PATH' => (string) getenv('PATH'),
			'GATEWAY_INTERFACE' => 'CGI/1.1',
			'REQUEST_METHOD' => 'GET',
			'REDIRECT_STATUS' => '200',
			'QUERY_STRING' => '',
			'SCRIPT_FILENAME' => DOCROOT.'tests/data/request_cgi.php',
		);

		$process = proc_open(
			array(Kohana_CgiTestCase::$cgi, '-d', 'display_errors=stderr', '-d', 'date.timezone=UTC'),
			array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')),
			$pipes, DOCROOT, $env
		);

		$output = stream_get_contents($pipes[1]);
		$stderr = stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);
		proc_close($process);

		$marker = strrpos($output, "\n@@RESULT@@");
		if ($marker === FALSE)
			$this->fail('The CGI request gave no result. Stderr: '.substr($stderr, 0, 500));

		$result = json_decode(substr($output, $marker + 11), TRUE);

		$result['cookies'] = array();
		foreach ($result['headers'] as $header)
		{
			if (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)(.*)$/i', $header, $matches))
			{
				// The last header for a name wins, as in a browser
				$result['cookies'][$matches[1]] = array(
					'value' => rawurldecode($matches[2]),
					'attributes' => $matches[3],
				);
			}
		}

		return $result;
	}

	/**
	 * Returns the cookies of a response as the next request sends them.
	 *
	 * @param   array  response from request()
	 * @param   array  cookies of the previous request
	 * @return  array
	 */
	protected function next_cookies(array $response, array $cookies = array())
	{
		foreach ($response['cookies'] as $name => $cookie)
		{
			if ($cookie['value'] === '' OR $cookie['value'] === 'deleted' OR preg_match('/expires=([^;]+)/i', $cookie['attributes'], $m) AND strtotime($m[1]) < time())
			{
				unset($cookies[$name]);
			}
			else
			{
				$cookies[$name] = $cookie['value'];
			}
		}

		return $cookies;
	}
}
