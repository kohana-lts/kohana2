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
}
