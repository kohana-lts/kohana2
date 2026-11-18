<?php
/**
 * html and form helpers escape data values (K2-13).
 */
class HelperEscapeTest extends Kohana_TestCase {

	const EVIL = '"><script>x</script>';
	const ESCAPED = '&quot;&gt;&lt;script&gt;x&lt;/script&gt;';

	protected function assertEscaped($html)
	{
		$this->assertStringNotContainsString('<script', $html);
		$this->assertStringContainsString(HelperEscapeTest::ESCAPED, $html);
	}

	public function test_anchor_default_title_is_escaped()
	{
		$this->assertEscaped(html::anchor('http://example.com/'.HelperEscapeTest::EVIL));
	}

	public function test_anchor_explicit_title_stays_raw()
	{
		$this->assertSame('<a href="http://example.com/"><b>x</b></a>', html::anchor('http://example.com/', '<b>x</b>'));
		$this->assertSame('<a href="http://example.com/">&lt;b&gt;</a>', html::anchor('http://example.com/', '<b>', NULL, NULL, TRUE));
	}

	public function test_file_anchor_default_title_is_escaped()
	{
		$this->assertEscaped(html::file_anchor('media/x'.HelperEscapeTest::EVIL));
		$this->assertStringEndsWith('>a.pdf</a>', html::file_anchor('media/a.pdf'));
	}

	public function test_mailto_params_and_address_are_escaped()
	{
		$html = html::mailto('a@example.com?subject='.HelperEscapeTest::EVIL);

		$this->assertStringNotContainsString('<script', $html);
		$this->assertStringNotContainsString('"><', $html);

		// html::email() picks raw characters at random, so try many times
		for ($i = 0; $i < 50; $i++)
		{
			$this->assertStringNotContainsString('"', html::email('"><b@x.y'));
		}
	}

	public function test_meta_is_escaped()
	{
		$this->assertEscaped(html::meta('description', HelperEscapeTest::EVIL));
		$this->assertEscaped(html::meta('x'.HelperEscapeTest::EVIL, 'y'));
	}

	public function test_script_src_is_escaped()
	{
		$this->assertSame('<script type="text/javascript" src="http://example.com/a.js'.HelperEscapeTest::ESCAPED.'"></script>'."\n", html::script('http://example.com/a.js'.HelperEscapeTest::EVIL));
	}

	public function test_attributes_drop_bad_keys()
	{
		$this->assertSame(' class="a"', html::attributes(array('class' => 'a', 'x onclick' => 'alert(1)', '"><b' => 'c', 'a=b' => 'd', '' => 'e')));
		$this->assertSame(' data-x="1" aria-label="&lt;b&gt;"', html::attributes(array('data-x' => 1, 'aria-label' => '<b>')));
	}

	public function test_breadcrumb_titles_are_escaped()
	{
		$crumbs = html::breadcrumb(array('news', '<script>x'));

		$this->assertStringNotContainsString('<script', implode('', $crumbs));
	}

	public function test_dropdown_keys_and_groups_are_escaped()
	{
		$html = form::dropdown('s', array(HelperEscapeTest::EVIL => 'a', 'g'.HelperEscapeTest::EVIL => array('k'.HelperEscapeTest::EVIL => 'b')), HelperEscapeTest::EVIL);

		$this->assertEscaped($html);
		$this->assertStringContainsString('value="'.HelperEscapeTest::ESCAPED.'" selected="selected"', $html);
	}

	public function test_dropdown_does_not_double_escape()
	{
		$html = form::dropdown('s', array(html::chars('a&b') => html::chars('c&d')));

		$this->assertStringContainsString('<option value="a&amp;b">c&amp;d</option>', $html);
	}

	public function test_label_derived_text_is_escaped()
	{
		$this->assertEscaped(form::label('x'.HelperEscapeTest::EVIL));
		$this->assertSame('<label for="a" ><b>A</b></label>', form::label('a', '<b>A</b>'));
	}

	public function test_button_value_is_escaped()
	{
		$this->assertEscaped(form::button('b', HelperEscapeTest::EVIL));
		$this->assertEscaped(form::button(array('name' => 'b', 'value' => HelperEscapeTest::EVIL)));
		$this->assertSame('<button name="b" >a &amp; b</button>', form::button('b', html::chars('a & b')));
		$this->assertSame('<button name="b" ><i>x</i></button>', form::button('b', '<i>x</i>', '', FALSE));
	}
}
