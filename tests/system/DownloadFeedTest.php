<?php
/**
 * download and feed helpers.
 */
class DownloadFeedTest extends Kohana_TestCase {

	public function test_disposition_plain_name()
	{
		$this->assertSame('attachment; filename="report 2010.pdf"', download::disposition('/tmp/report 2010.pdf'));
	}

	public function test_disposition_escapes_quotes_and_parameters()
	{
		$value = download::disposition('a".txt"; filename=evil.exe');

		$this->assertSame('attachment; filename="a_.txt_; filename=evil.exe"; filename*=UTF-8\'\'a%22.txt%22%3B%20filename%3Devil.exe', $value);
	}

	public function test_disposition_utf8_and_backslash()
	{
		$this->assertSame("attachment; filename=\"__eli__.txt\"; filename*=UTF-8''%C5%A1eli%5C%25.txt", download::disposition('šeli\\%.txt'));
	}

	protected function rss()
	{
		return '<?xml version="1.0"?><rss version="2.0"><channel><title>t</title><item><title>one</title></item><item><title>two</title></item></channel></rss>';
	}

	public function test_parse_string()
	{
		$items = feed::parse($this->rss());

		$this->assertCount(2, $items);
		$this->assertSame('one', $items[0]['title']);
	}

	public function test_parse_does_not_read_a_file_by_default()
	{
		$file = tempnam(sys_get_temp_dir(), 'feed');
		file_put_contents($file, $this->rss());

		try
		{
			$this->assertSame(array(), feed::parse($file));
			$this->assertCount(2, feed::parse($file, 0, 'file'));
		}
		finally
		{
			unlink($file);
		}
	}

	public function test_parse_url_allows_only_http()
	{
		$file = tempnam(sys_get_temp_dir(), 'feed');
		file_put_contents($file, $this->rss());

		try
		{
			$this->assertSame(array(), feed::parse('file://'.$file, 0, 'url'));
			$this->assertSame(array(), feed::parse('php://filter/resource='.$file, 0, 'url'));
			$this->assertSame(array(), feed::parse('file://'.$file));
		}
		finally
		{
			unlink($file);
		}
	}
}
