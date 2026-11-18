<?php
require_once DOCROOT.'tests/CgiTestCase.php';

/**
 * @group url
 */
class UrlHttpTest extends Kohana_CgiTestCase {

	protected function location(array $response)
	{
		foreach ($response['headers'] as $header)
		{
			if (stripos($header, 'Location:') === 0)
				return trim(substr($header, 9));
		}

		return NULL;
	}

	public function test_redirect_does_not_use_the_host_header()
	{
		$response = $this->request(array(array('call', 'url::redirect', 'welcome')), array(), array('core.site_domain' => '/kohana/', 'core.site_protocol' => ''), array('HTTP_HOST' => 'evil.example'));

		$this->assertSame('/kohana/index.php/welcome', $this->location($response));
	}

	public function test_redirect_keeps_absolute_urls()
	{
		$response = $this->request(array(array('call', 'url::redirect', 'https://other.example/path')));

		$this->assertSame('https://other.example/path', $this->location($response));
	}
}
