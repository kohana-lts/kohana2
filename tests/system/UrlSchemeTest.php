<?php
/**
 * valid::url() and html::anchor() refuse script schemes (K2-14).
 */
class UrlSchemeTest extends Kohana_TestCase {

	public function test_valid_url_allows_http_and_https_by_default()
	{
		$this->assertTrue(valid::url('http://example.com/'));
		$this->assertTrue(valid::url('HTTPS://example.com/a?b=c'));
		$this->assertFalse(valid::url('javascript://example.com/%0Aalert(1)'));
		$this->assertFalse(valid::url('ftp://example.com/file'));
		$this->assertFalse(valid::url('data://text/plain,x'));
		$this->assertFalse(valid::url('not a url'));
	}

	public function test_valid_url_scheme_list()
	{
		$this->assertTrue(valid::url('ftp://example.com/file', array('ftp')));
		$this->assertTrue(valid::url('FTP://example.com/file', array('FTP', 'http')));
		$this->assertFalse(valid::url('https://example.com/', 'http'));
		$this->assertTrue(valid::url('javascript://example.com/%0Aalert(1)', NULL));
	}

	public function test_validation_rule_arguments()
	{
		$post = new Validation(array('a' => 'ftp://example.com/', 'b' => 'javascript://x/%0Aalert(1)', 'c' => 'ftp://example.com/'));
		$post->add_rules('a', 'url[ftp]');
		$post->add_rules('b', 'url');
		$post->add_rules('c', 'url');

		$this->assertFalse($post->validate());
		$this->assertSame(array('b', 'c'), array_keys($post->errors()));
	}

	public function provider_bad_hrefs()
	{
		return array
		(
			array('javascript://example.com/%0Aalert(1)'),
			array('JavaScript://x/%0Aalert(1)'),
			array("java\tscript://x/%0Aalert(1)"),
			array('&#106;avascript://x/%0Aalert(1)'),
			array('vbscript://x/%0Amsgbox(1)'),
			array('data://text/html,x'),
		);
	}

	/**
	 * @dataProvider provider_bad_hrefs
	 */
	public function test_anchor_refuses_script_schemes($uri)
	{
		$html = html::anchor($uri, 'x');

		$this->assertSame('<a href="#">x</a>', $html);
	}

	public function test_anchor_keeps_other_urls()
	{
		$this->assertSame('<a href="https://example.com/?a=1&amp;b=2">x</a>', html::anchor('https://example.com/?a=1&b=2', 'x'));
		$this->assertSame('<a href="ftp://example.com/f">x</a>', html::anchor('ftp://example.com/f', 'x'));
		$this->assertSame('<a href="#top">x</a>', html::anchor('#top', 'x'));
	}

	public function test_anchor_scheme_opt_out()
	{
		$blocked = html::$blocked_schemes;
		html::$blocked_schemes = array();

		try
		{
			$this->assertSame('<a href="data://text/html,x">x</a>', html::anchor('data://text/html,x', 'x'));
		}
		finally
		{
			html::$blocked_schemes = $blocked;
		}
	}
}
