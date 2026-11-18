<?php
/**
 * The shipped Welcome controller escapes the URI (K2-03).
 */
class WelcomeControllerTest extends Kohana_TestCase {

	public function test_call_escapes_the_uri()
	{
		$uri = Router::$current_uri;
		Router::$current_uri = 'welcome/x<script>alert(3)</script>';

		$controller = (new ReflectionClass('Welcome_Controller'))->newInstanceWithoutConstructor();

		try
		{
			ob_start();
			$controller->missing_page();
			$html = ob_get_clean();
		}
		finally
		{
			Router::$current_uri = $uri;
		}

		$this->assertStringNotContainsString('<script', $html);
		$this->assertStringContainsString('welcome/index/x&lt;script&gt;', $html);
	}
}
