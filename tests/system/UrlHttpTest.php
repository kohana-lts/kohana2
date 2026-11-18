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

	public function provider_redirect_local()
	{
		return array(
			array('account', '/kohana/index.php/account'),
			array('/kohana/index.php/account', '/kohana/index.php/account'),
			array('//evil.example', '/kohana/index.php/'),
			array('/\\evil.example', '/kohana/index.php/'),
			array('https://evil.example/', '/kohana/index.php/'),
			array('javascript:alert(1)', '/kohana/index.php/'),
			array('/kohana/http://evil.example', '/kohana/index.php/'),
			array('/kohana/index.php/https://evil.example/', '/kohana/index.php/'),
			array('/kohana///evil.example?x=://', '/kohana/index.php/'),
			array('https://www.example.com/kohana/http://evil.example', '/kohana/index.php/'),
			array('account?next=http://x', '/kohana/index.php/account?next=http://x'),
		);
	}

	/**
	 * @dataProvider provider_redirect_local
	 */
	public function test_redirect_local($uri, $location)
	{
		$response = $this->request(array(array('call', 'url::redirect_local', $uri)), array(), array('core.site_domain' => '/kohana/', 'core.site_protocol' => '', 'core.allowed_hosts' => array('www.example.com')));

		$this->assertSame($location, $this->location($response));
	}
}
