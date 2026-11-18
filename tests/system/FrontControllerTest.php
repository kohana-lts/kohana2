<?php
/**
 * index.php and the shipped controllers (K2-21).
 */
class FrontControllerTest extends Kohana_TestCase {

	protected function install_page_shown(array $env)
	{
		$output = $this->run_child('front_controller.php', $env);

		return strpos($output, '<title>Kohana Installation</title>') !== FALSE;
	}

	public function test_install_page_runs_outside_production()
	{
		if ( ! is_file(DOCROOT.'install.php'))
			$this->markTestSkipped('install.php is not present');

		$this->assertTrue($this->install_page_shown(array('KOHANA_ENV' => 'development')), 'The install page did not run');
	}

	public function test_install_page_does_not_run_in_production()
	{
		$this->assertFalse($this->install_page_shown(array('KOHANA_ENV' => 'PRODUCTION')), 'The install page ran with KOHANA_ENV=PRODUCTION');
		$this->assertFalse($this->install_page_shown(array('KOHANA_ENV' => 'production')), 'The install page ran with KOHANA_ENV=production');
	}

	public function test_error_page_has_no_details_in_production()
	{
		$output = $this->run_child('front_controller.php', array('KOHANA_ENV' => 'PRODUCTION'), array('nosuch/page'));

		$this->assertStringContainsString('Unable to Complete Request', $output);
		$this->assertStringNotContainsString('could not be found', $output);
		$this->assertStringNotContainsString('Kohana_404_Exception', $output);
		$this->assertStringNotContainsString(SYSPATH, $output);
	}

	public function test_orphan_captcha_controller_is_removed()
	{
		$this->assertFalse(Kohana::find_file('controllers', 'captcha'));
	}
}
