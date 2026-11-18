<?php
/**
 * Kohana_PHP_Exception::shutdown_handler() must report only fatal errors.
 * Other errors already went through the error handler, or code silenced them.
 */
class ShutdownHandlerTest extends Kohana_TestCase {

	public function test_silenced_warning_gives_no_error_page()
	{
		$output = json_decode($this->run_child('shutdown_silenced.php'), TRUE);

		$this->assertSame(array('ok' => TRUE), $output, 'The handler showed an error page');
	}
}
