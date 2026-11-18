<?php
/**
 * Input library: global data cleaning.
 */
class InputTest extends Kohana_TestCase {

	protected $globals;
	protected $instance;

	protected function setUp(): void
	{
		parent::setUp();

		$this->globals = array($_GET, $_POST, $_COOKIE, $_SERVER);
		$this->instance = $this->instance_property()->getValue();
	}

	protected function tearDown(): void
	{
		list($_GET, $_POST, $_COOKIE, $_SERVER) = $this->globals;
		$this->instance_property()->setValue(NULL, $this->instance);

		parent::tearDown();
	}

	protected function instance_property()
	{
		return new ReflectionProperty('Input', 'instance');
	}

	/**
	 * Returns a new Input that cleans the globals again.
	 */
	protected function fresh_input()
	{
		$this->instance_property()->setValue(NULL, NULL);

		return new Input;
	}

	public function test_bad_cookie_key_is_dropped_without_exit()
	{
		$_COOKIE = array('good' => 'a', 'bad key' => 'b', 'bad<key>' => 'c', '$Version' => '1');
		$_GET = array('q' => 'x', 'a b' => 'y', 'list' => array('ok' => 1, 'not ok' => 2));

		$this->fresh_input();

		$this->assertSame(array('good' => 'a', '$Version' => '1'), $_COOKIE);
		$this->assertSame(array('q' => 'x', 'list' => array('ok' => 1)), $_GET);
	}

	public function test_clean_input_keys_returns_false_for_bad_key()
	{
		$input = Input::instance();

		$this->assertSame('a.b-c:d_e', $input->clean_input_keys('a.b-c:d_e'));
		$this->assertSame(5, $input->clean_input_keys(5));
		$this->assertFalse($input->clean_input_keys("bad\nkey"));
	}

	public function test_clean_removes_changed_keys()
	{
		$clean = Input::clean(array("a\x01b" => 'x', 'c' => array("d\x02" => 'y')));

		ksort($clean);

		$this->assertSame(array('ab' => 'x', 'c' => array('d' => 'y')), $clean);
	}

	/**
	 * Returns the client IP for the given server values (K2-09).
	 */
	protected function ip(array $server, array $proxies = array())
	{
		$this->set_config('input.trusted_proxies', $proxies);

		unset($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_CLIENT_IP'], $_SERVER['REMOTE_ADDR']);
		$_SERVER = $server + $_SERVER;

		$input = Input::instance();
		$input->ip_address = NULL;

		return $input->ip_address();
	}

	public function test_ip_ignores_forwarded_headers_without_trusted_proxy()
	{
		$this->assertSame('203.0.113.9', $this->ip(array('REMOTE_ADDR' => '203.0.113.9', 'HTTP_X_FORWARDED_FOR' => '6.6.6.6')));
		$this->assertSame('203.0.113.9', $this->ip(array('REMOTE_ADDR' => '203.0.113.9', 'HTTP_CLIENT_IP' => '7.7.7.7')));
		$this->assertSame('203.0.113.9', $this->ip(array('REMOTE_ADDR' => '203.0.113.9', 'HTTP_X_FORWARDED_FOR' => '6.6.6.6'), array('10.0.0.0/8')));
	}

	public function test_ip_uses_right_most_untrusted_hop()
	{
		$proxies = array('10.0.0.0/8', '192.0.2.1');

		$this->assertSame('198.51.100.7', $this->ip(array('REMOTE_ADDR' => '10.1.2.3', 'HTTP_X_FORWARDED_FOR' => '6.6.6.6, 198.51.100.7'), $proxies));
		$this->assertSame('198.51.100.7', $this->ip(array('REMOTE_ADDR' => '10.1.2.3', 'HTTP_X_FORWARDED_FOR' => '6.6.6.6, 198.51.100.7, 192.0.2.1,10.9.9.9'), $proxies));
		$this->assertSame('198.51.100.8', $this->ip(array('REMOTE_ADDR' => '192.0.2.1', 'HTTP_CLIENT_IP' => '198.51.100.8'), $proxies));
	}

	public function test_ip_stops_at_invalid_hop()
	{
		$proxies = array('10.0.0.0/8');

		$this->assertSame('10.0.0.2', $this->ip(array('REMOTE_ADDR' => '10.1.2.3', 'HTTP_X_FORWARDED_FOR' => '6.6.6.6, <script>, 10.0.0.2'), $proxies));
		$this->assertSame('10.1.2.3', $this->ip(array('REMOTE_ADDR' => '10.1.2.3', 'HTTP_X_FORWARDED_FOR' => 'unknown'), $proxies));
	}

	public function test_ip_ipv6_and_invalid_remote_addr()
	{
		$this->assertSame('2001:db8::1', $this->ip(array('REMOTE_ADDR' => '2001:db8::1')));
		$this->assertSame('127.0.0.1', $this->ip(array('REMOTE_ADDR' => '127.0.0.1')));
		$this->assertSame('2001:db8::7', $this->ip(array('REMOTE_ADDR' => '2001:db8:1::5', 'HTTP_X_FORWARDED_FOR' => '2001:db8::7'), array('2001:db8:1::/48')));
		$this->assertSame('0.0.0.0', $this->ip(array('REMOTE_ADDR' => 'x')));
		$this->assertSame('0.0.0.0', $this->ip(array()));
	}

	public function test_ip_in_range()
	{
		$this->assertTrue(Input::ip_in_range('10.20.30.40', '10.0.0.0/8'));
		$this->assertFalse(Input::ip_in_range('11.0.0.1', '10.0.0.0/8'));
		$this->assertTrue(Input::ip_in_range('192.168.1.130', '192.168.1.128/25'));
		$this->assertFalse(Input::ip_in_range('192.168.1.127', '192.168.1.128/25'));
		$this->assertTrue(Input::ip_in_range('192.0.2.1', '192.0.2.1'));
		$this->assertTrue(Input::ip_in_range('1.2.3.4', '0.0.0.0/0'));
		$this->assertTrue(Input::ip_in_range('2001:db8::1', '2001:db8::/32'));
		$this->assertFalse(Input::ip_in_range('2001:db9::1', '2001:db8::/32'));
		$this->assertFalse(Input::ip_in_range('10.0.0.1', '2001:db8::/32'));
		$this->assertFalse(Input::ip_in_range('10.0.0.1', '10.0.0.0/33'));
		$this->assertFalse(Input::ip_in_range('10.0.0.1', 'bad'));
	}
}
