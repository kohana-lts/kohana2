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
}
