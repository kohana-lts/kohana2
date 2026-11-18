<?php
/**
 * The debug error page escapes the exception and request data (K2-02).
 * Never print the page in a failure message: it contains $_SERVER.
 */
class ErrorViewTest extends Kohana_TestCase {

	protected $get;

	protected function setUp(): void
	{
		parent::setUp();

		$this->get = $_GET;
	}

	protected function tearDown(): void
	{
		$_GET = $this->get;

		parent::tearDown();
	}

	protected function render_error(array $vars)
	{
		extract($vars + array
		(
			'type'    => 'Kohana_Exception',
			'code'    => 'Error',
			'message' => 'message',
			'file'    => __FILE__,
			'line'    => 1,
			'trace'   => array(),
		));

		ob_start();
		include SYSPATH.'views/kohana/error.php';

		return ob_get_clean();
	}

	protected function assertNoTag($html, $what)
	{
		$this->assertTrue(strpos($html, '<x-evil') === FALSE, 'Raw markup from '.$what.' in the error page');
		$this->assertTrue(strpos($html, '&lt;x-evil') !== FALSE, 'Escaped markup from '.$what.' is missing in the error page');
	}

	public function test_message_type_and_code_are_escaped()
	{
		$this->assertNoTag($this->render_error(array('message' => 'The page you requested, nothere<x-evil>, could not be found.')), 'message');
		$this->assertNoTag($this->render_error(array('type' => 'T<x-evil>')), 'type');
		$this->assertNoTag($this->render_error(array('code' => 'C<x-evil>')), 'code');
	}

	public function test_file_and_trace_are_escaped()
	{
		$this->assertNoTag($this->render_error(array('file' => '/tmp/<x-evil>.php')), 'file');

		$trace = array(array('function' => 'fn<x-evil>', 'file' => '/tmp/a<x-evil>.php', 'line' => 3));

		$this->assertNoTag($this->render_error(array('trace' => $trace)), 'trace');
	}

	public function test_global_keys_are_escaped()
	{
		$_GET = array('k<x-evil>' => 'v');

		$this->assertNoTag($this->render_error(array()), 'a $_GET key');
	}

	public function test_disabled_page_escapes_the_uri()
	{
		$uri = Router::$current_uri;
		Router::$current_uri = 'x"><x-evil>';

		try
		{
			ob_start();
			include SYSPATH.'views/kohana/error_disabled.php';
			$html = ob_get_clean();
		}
		finally
		{
			Router::$current_uri = $uri;
		}

		$this->assertNoTag($html, 'the URI');
	}
}
