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
