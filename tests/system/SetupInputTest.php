<?php
/**
 * The routing start-up cleans the global input on every request (K2-10).
 */
class SetupInputTest extends Kohana_TestCase {

	public function test_setup_cleans_global_input()
	{
		$output = $this->run_child('global_input.php', array('KOHANA_TEST_CONFIG' => json_encode(array('core.global_xss_filtering' => FALSE))));
		$globals = json_decode($output, TRUE);

		$this->assertIsArray($globals, 'The child process did not print JSON');
		$this->assertSame(array('x' => '<script>alert(1)</script>', 'c' => 'ab'), $globals['get']);
		$this->assertSame(array('ok' => 'y'), $globals['cookie']);
	}

	public function test_setup_applies_global_xss_filtering()
	{
		$output = $this->run_child('global_input.php', array('KOHANA_TEST_CONFIG' => json_encode(array('core.global_xss_filtering' => TRUE))));
		$globals = json_decode($output, TRUE);

		$this->assertIsArray($globals, 'The child process did not print JSON');
		$this->assertStringNotContainsString('<script', $globals['get']['x']);
		$this->assertStringNotContainsString('javascript:', $globals['post']['p']);
	}

	public function test_routing_cleans_cli_query()
	{
		$output = $this->run_child('global_input.php', array('KOHANA_TEST_CONFIG' => json_encode(array('core.global_xss_filtering' => TRUE))), array('welcome/index?q=<script>alert(1)</script>&bad<key=1&ok=2'));
		$globals = json_decode($output, TRUE);

		$this->assertIsArray($globals, 'The child process did not print JSON');
		$this->assertSame(array('q', 'ok'), array_keys($globals['get']));
		$this->assertStringNotContainsString('<script', $globals['get']['q']);
	}
}
