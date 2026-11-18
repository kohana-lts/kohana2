<?php
/**
 * Input::xss_clean() with the default filter.
 */
class InputXssTest extends Kohana_TestCase {

	protected function clean($data)
	{
		return Input::instance()->xss_clean($data);
	}

	/**
	 * Payloads of the upstream tickets #2669, #2710 and #2771 (K2-01).
	 */
	public function provider_ticket_payloads()
	{
		return array
		(
			'#2710 newline before expression' => array("<span style=\"width:\nexpression(alert(1));\">x</span>", 'expression('),
			'#2710 newline in style' => array("<div style=\"\nbehaviour(alert(1))\">x</div>", 'behaviour('),
			'#2710 script in style' => array("<div style=\"x:\nscript:alert(1)\">x</div>", 'script:'),
			'#2669 null byte in tag' => array("<\0script>alert(1)</script>", 'script'),
			'#2771 space before slash' => array('<script>alert(1)< /script>', 'script'),
			'#2771 space before name' => array('< script>alert(1)</script>', 'script'),
			'#2771 null byte before slash' => array("<script>alert(1)<\0/script>", 'script'),
		);
	}

	/**
	 * @dataProvider provider_ticket_payloads
	 */
	public function test_ticket_payloads_are_cleaned($payload, $forbidden)
	{
		$this->assertStringNotContainsStringIgnoringCase($forbidden, $this->clean($payload));
	}

	/**
	 * Decodes entities until the value is stable, then removes the
	 * characters that browsers ignore in a URL scheme.
	 */
	protected function normalize($html)
	{
		do
		{
			$old = $html;
			$html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		}
		while ($old !== $html);

		return strtolower(preg_replace('/[\x00-\x20]+/', '', $html));
	}

	/**
	 * Scheme bypasses (K2-11).
	 */
	public function provider_scheme_payloads()
	{
		return array
		(
			'html5 colon entity' => array('<a href="javascript&colon;alert(1)">x</a>', 'javascript:'),
			'html5 tab entity' => array('<a href="jav&Tab;ascript:alert(1)">x</a>', 'javascript:'),
			'html5 newline entity' => array('<a href="java&NewLine;script:alert(1)">x</a>', 'javascript:'),
			'double encoded colon' => array('<a href="javascript&#38;colon;alert(1)">x</a>', 'javascript:'),
			'double encoded letter' => array('<a href="&amp;#106;avascript:alert(1)">x</a>', 'javascript:'),
			'data uri' => array('<a href="data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==">x</a>', 'data:'),
			'data uri split' => array("<a href=\"da\tta:text/html,<script>alert(1)</script>\">x</a>", 'data:'),
			'data uri entity' => array('<a href="&#100;ata:text/html,x">x</a>', 'data:'),
			'data uri unquoted' => array('<iframe src=data:text/html,x></iframe><img src=data:text/html,x>', 'data:'),
			'form action' => array('<form action="data:text/html,x"><button formaction="java&Tab;script:alert(1)">x</button></form>', 'script:'),
			'vbscript' => array('<a href="vb&Tab;script:msgbox(1)">x</a>', 'vbscript:'),
			'split by on attribute' => array('<a href="daonx=\'\'ta:text/html,x">x</a>', 'data:'),
			'split by namespaced tag' => array('<a href="da<a:b>ta:text/html,x">x</a>', 'data:'),
			'split by script tag' => array('<a href="da<script>ta:text/html,x">x</a>', 'data:'),
			'style url' => array('<div style="background:url(jav&Tab;ascript:alert(1))">x</div>', 'javascript:'),
		);
	}

	/**
	 * @dataProvider provider_scheme_payloads
	 */
	public function test_dangerous_schemes_are_removed($payload, $scheme)
	{
		$this->assertStringNotContainsString($scheme, $this->normalize($this->clean($payload)));
	}

	public function test_uppercase_entities_do_not_create_tags()
	{
		$this->assertStringNotContainsString('<a', $this->clean('&LT;a href="x"&GT;'));
	}

	/**
	 * Safe markup must not change.
	 */
	public function provider_safe_markup()
	{
		return array
		(
			array('<a href="http://example.com/?a=1&amp;b=2">x</a>'),
			array('<a href="https://example.com/" title="data: is text">x</a>'),
			array('<p>Use data: and javascript: in text.</p>'),
			array('<img src="/images/a.png" alt="a" />'),
			array('<a href="https://example.com/?q=data:x&amp;r=2">x</a>'),
		);
	}

	/**
	 * @dataProvider provider_safe_markup
	 */
	public function test_safe_markup_is_kept($html)
	{
		$this->assertSame($html, $this->clean($html));
	}
}
