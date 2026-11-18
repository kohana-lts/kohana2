<?php
/**
 * security::strip_image_tags() (CVE-2016-10510, the same regular expression
 * as Kohana 3.3).
 */
class SecurityHelperTest extends Kohana_TestCase {

	public function provider_payloads()
	{
		return array(
			array('<img/src=x onerror=prompt(document.domain)>'),
			array('<img src=x onerror=alert(1)>'),
			array('<IMG SRC=x OnErRoR=alert(1)>'),
			array("<img\tsrc=x\nonerror=alert(1)>"),
			array('<img src="x" onerror="alert(1)"'),
			array('<im<img src=x>g src=x onerror=alert(1)>'),
			array('<image src=x onerror=alert(1)>'),
			array('<img/onerror=alert(1)/src=x>'),
		);
	}

	/**
	 * @dataProvider provider_payloads
	 */
	public function test_strip_image_tags_removes_every_image_tag($payload)
	{
		$output = security::strip_image_tags($payload);

		$this->assertDoesNotMatchRegularExpression('#<\s*im(?:g|age)\b#i', $output);
	}

	public function test_strip_image_tags_keeps_the_source()
	{
		$this->assertSame('a pic.png b', security::strip_image_tags('a <img src="pic.png" alt="x" /> b'));
		$this->assertSame('a &lt;b&gt; b', security::strip_image_tags('a <img src="&lt;b&gt;"> b'));
		$this->assertSame('text', security::strip_image_tags('text'));
	}
}
