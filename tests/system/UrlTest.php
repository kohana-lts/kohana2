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

	public function provider_local_uri()
	{
		return array(
			array('', ''),
			array('welcome', 'welcome'),
			array('blog/post?page=2#comments', 'blog/post?page=2#comments'),
			array('/kohana/index.php/account', 'account'),
			array('/kohana/index.php', ''),
			array('/kohana/', ''),
			array('/kohana', ''),
			array('/kohana/media/x.css', 'media/x.css'),
			array('https://www.example.com/kohana/index.php/account?x=1', 'account?x=1'),
			array('http://example.com:8080/kohana/index.php/a', 'a'),
			array('/other/app', NULL),
			array('//evil.example', NULL),
			array('///evil.example', NULL),
			array('/\\evil.example', NULL),
			array('\\\\evil.example', NULL),
			array("\t//evil.example", NULL),
			array(" //evil.example", NULL),
			array("/kohana/\r\nX-Injected: 1", NULL),
			array('https://evil.example/', NULL),
			array('https://www.example.com.evil.example/kohana/', NULL),
			array('http://www.example.com@evil.example/kohana/', NULL),
			array('http:evil.example', NULL),
			array('javascript:alert(1)', NULL),
			array('ftp://www.example.com/kohana/', NULL),
			array('/kohana/http://evil.example', NULL),
			array('/kohana/index.php/https://evil.example/', NULL),
			array('/kohana///evil.example?x=://', NULL),
			array('/kohana/index.php//evil.example', NULL),
			array('https://www.example.com/kohana/http://evil.example', NULL),
			array('account?next=http://x', 'account?next=http://x'),
			array(array('welcome'), NULL),
		);
	}

	/**
	 * @dataProvider provider_local_uri
	 */
	public function test_local_uri($uri, $expected)
	{
		$_SERVER['HTTP_HOST'] = 'evil.example';

		$this->assertSame($expected, url::local_uri($uri));
	}

	public function test_local_uri_with_wildcard_hosts()
	{
		$this->set_config('core.allowed_hosts', array('*'));
		$_SERVER['HTTP_HOST'] = 'evil.example';

		$this->assertNull(url::local_uri('https://evil.example/kohana/index.php/a'));

		$this->set_config('core.site_domain', 'site.example/kohana/');
		$this->assertSame('a', url::local_uri('https://site.example/kohana/index.php/a'));
	}
}
