<?php
/**
 * @group url
 */
class UrlTest extends Kohana_TestCase {

	protected $server;

	protected function setUp(): void
	{
		parent::setUp();

		$this->server = $_SERVER;

		$this->set_config('core.site_domain', '/kohana/');
		$this->set_config('core.site_protocol', '');
		$this->set_config('core.index_page', 'index.php');
		$this->set_config('core.url_suffix', '');
		$this->set_config('core.allowed_hosts', array('www.example.com', 'example.com:8080'));
	}

	protected function tearDown(): void
	{
		$_SERVER = $this->server;

		parent::tearDown();
	}

	public function test_relative_urls_do_not_use_the_host()
	{
		$_SERVER['HTTP_HOST'] = 'evil.example';

		$this->assertSame('/kohana/', url::base());
		$this->assertSame('/kohana/index.php/welcome', url::site('welcome'));
	}

	public function test_allowed_host_is_used()
	{
		$_SERVER['HTTP_HOST'] = 'WWW.Example.com';
		$this->assertSame('http://www.example.com/kohana/index.php/welcome', url::site('welcome', 'http'));

		$_SERVER['HTTP_HOST'] = 'example.com:8080';
		$this->assertSame('https://example.com:8080/kohana/', url::base(FALSE, 'https'));
	}

	public function provider_bad_hosts()
	{
		return array(
			array('evil.example'),
			array('www.example.com.evil.example'),
			array('evil.example/www.example.com'),
			array('www.example.com@evil.example'),
			array(''),
			array(NULL),
		);
	}

	/**
	 * @dataProvider provider_bad_hosts
	 */
	public function test_other_host_gives_the_first_allowed_host($host)
	{
		if ($host === NULL)
		{
			unset($_SERVER['HTTP_HOST']);
		}
		else
		{
			$_SERVER['HTTP_HOST'] = $host;
		}

		$this->assertSame('https://www.example.com/kohana/index.php/reset', url::site('reset', 'https'));
	}

	public function test_empty_list_fails_closed()
	{
		$this->set_config('core.allowed_hosts', NULL);
		$_SERVER['HTTP_HOST'] = 'evil.example';

		$this->expectException('Kohana_Exception');
		url::base(FALSE, 'http');
	}

	public function test_wildcard_allows_any_valid_host()
	{
		$this->set_config('core.allowed_hosts', array('*'));

		$_SERVER['HTTP_HOST'] = 'any.example';
		$this->assertSame('http://any.example/kohana/', url::base(FALSE, 'http'));

		$_SERVER['HTTP_HOST'] = 'evil.example/x?';
		$this->expectException('Kohana_Exception');
		url::base(FALSE, 'http');
	}

	public function test_site_domain_with_host_ignores_the_header()
	{
		$this->set_config('core.site_domain', 'site.example/kohana/');
		$this->set_config('core.allowed_hosts', array());
		$_SERVER['HTTP_HOST'] = 'evil.example';

		$this->assertSame('https://site.example/kohana/', url::base(FALSE, 'https'));
	}

	public function test_site_protocol_uses_the_allowed_hosts()
	{
		$this->set_config('core.site_protocol', 'https');
		$_SERVER['HTTP_HOST'] = 'evil.example';

		$this->assertSame('https://www.example.com/kohana/index.php/', url::site());
	}
}
