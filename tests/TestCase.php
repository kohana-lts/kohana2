<?php
/**
 * Base class for the Kohana 2 tests.
 */
abstract class Kohana_TestCase extends \PHPUnit\Framework\TestCase {

	/**
	 * Sets a config value for one test. tearDown() restores it.
	 *
	 * @var array  key => original value
	 */
	protected $config_backup = array();

	protected function set_config($key, $value)
	{
		if ( ! array_key_exists($key, $this->config_backup))
		{
			$this->config_backup[$key] = Kohana::config($key);
		}

		Kohana_Config::instance()->set($key, $value);
	}

	/**
	 * Runs a PHP file from tests/data/child in a new PHP process and returns
	 * its output. Use it for code that runs once per process (Kohana::setup(),
	 * IN_PRODUCTION). Do not print the output in a failure message: an error
	 * page in it contains $_SERVER.
	 *
	 * @param   string  $script  file name in tests/data/child
	 * @param   array   $env     extra environment values
	 * @param   array   $args    extra command line arguments
	 * @return  string
	 */
	protected function run_child($script, array $env = array(), array $args = array())
	{
		$command = array_merge(array(PHP_BINARY, __DIR__.'/data/child/'.$script), $args);

		$process = proc_open($command, array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes, DOCROOT, $env + getenv());

		$output = stream_get_contents($pipes[1]);
		stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);
		proc_close($process);

		return $output;
	}

	protected function tearDown(): void
	{
		foreach ($this->config_backup as $key => $value)
		{
			Kohana_Config::instance()->set($key, $value);
		}

		$this->config_backup = array();

		parent::tearDown();
	}
}
