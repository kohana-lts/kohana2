<?php
require_once DOCROOT.'tests/CgiTestCase.php';

/**
 * @group cookie
 */
class CookieHttpTest extends Kohana_CgiTestCase {

	public function test_set_sends_signed_value_with_httponly_and_samesite()
	{
		$response = $this->request(array(array('call', 'cookie::set', 'name', 'value')));

		$this->assertNull($response['error']);
		$this->assertSame(array(TRUE), $response['results']);

		$cookie = $response['cookies']['name'];
		$this->assertMatchesRegularExpression('/^[0-9a-f]{64}~value$/D', $cookie['value']);
		$this->assertStringContainsString('; HttpOnly', $cookie['attributes']);
		$this->assertStringContainsString('; SameSite=Lax', $cookie['attributes']);
		$this->assertStringNotContainsString('; secure', $cookie['attributes']);
	}

	public function test_samesite_parameter_and_option()
	{
		$response = $this->request(array(
			array('call', 'cookie::set', 'a', 'value', NULL, NULL, NULL, NULL, NULL, 'strict'),
			array('call', 'cookie::set', array('name' => 'b', 'value' => 'value', 'samesite' => '', 'httponly' => FALSE)),
		));

		$this->assertNull($response['error']);
		$this->assertStringContainsString('; SameSite=Strict', $response['cookies']['a']['attributes']);
		$this->assertStringNotContainsString('SameSite', $response['cookies']['b']['attributes']);
		$this->assertStringNotContainsString('HttpOnly', $response['cookies']['b']['attributes']);

		$response = $this->request(array(array('call', 'cookie::set', 'a', 'value', NULL, NULL, NULL, NULL, NULL, "Lax\r\nX-Injected: 1")));

		$this->assertStringContainsString('SameSite', (string) $response['error']);
		$this->assertArrayNotHasKey('a', $response['cookies']);
	}

	public function test_delete_works_without_salt()
	{
		$response = $this->request(array(array('call', 'cookie::delete', 'name')), array(), array('cookie.salt' => ''));

		$this->assertNull($response['error']);
		$this->assertContains($response['cookies']['name']['value'], array('', 'deleted'));
		$this->assertMatchesRegularExpression('/expires=[^;]+1970|Max-Age=0/i', $response['cookies']['name']['attributes']);
	}

	public function test_session_cookie_has_httponly_and_samesite()
	{
		$response = $this->request(array(array('start')));

		$this->assertNull($response['error']);

		$cookie = $response['cookies']['kohanasession'];
		$this->assertStringContainsString('; HttpOnly', $cookie['attributes']);
		$this->assertStringContainsString('; SameSite=Lax', $cookie['attributes']);
	}

	public function test_session_refuses_empty_salt()
	{
		$response = $this->request(array(array('start')), array(), array('cookie.salt' => ''));

		$this->assertStringContainsString('cookie.salt', (string) $response['error']);
	}
}
