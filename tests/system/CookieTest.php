<?php
/**
 * @group cookie
 */
class CookieTest extends Kohana_TestCase {

	const SALT = 'cookie-test-salt-Tg5Wq8Zr2Lm6Xv9Nc3Bd7Hk1';

	protected $cookies;
	protected $agent;

	protected function setUp(): void
	{
		parent::setUp();

		$this->cookies = $_COOKIE;
		$this->agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : NULL;

		$this->set_config('cookie.salt', CookieTest::SALT);
		$this->set_config('cookie.user_agent', FALSE);
	}

	protected function tearDown(): void
	{
		$_COOKIE = $this->cookies;

		if ($this->agent === NULL)
		{
			unset($_SERVER['HTTP_USER_AGENT']);
		}
		else
		{
			$_SERVER['HTTP_USER_AGENT'] = $this->agent;
		}

		parent::tearDown();
	}

	public function test_signed_value_is_read()
	{
		$_COOKIE['name'] = cookie::salt('name', 'value').'~value';
		$this->assertSame('value', cookie::get('name'));

		$_COOKIE['empty'] = cookie::salt('empty', '').'~';
		$this->assertSame('', cookie::get('empty'));

		$_COOKIE['tilde'] = cookie::salt('tilde', 'a~b').'~a~b';
		$this->assertSame('a~b', cookie::get('tilde'));
	}

	public function test_signature_is_hmac_sha256()
	{
		$this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/D', cookie::salt('name', 'value'));
		$this->assertSame(hash_hmac('sha256', '4:name|0:|value', CookieTest::SALT), cookie::salt('name', 'value'));

		$this->set_config('cookie.salt', 'other salt');
		$this->assertNotSame(hash_hmac('sha256', '4:name|0:|value', CookieTest::SALT), cookie::salt('name', 'value'));
	}

	public function test_name_and_value_split_is_part_of_the_signature()
	{
		$this->assertNotSame(cookie::salt('ab', 'c'), cookie::salt('a', 'bc'));

		// A valid signature for (ab, c) must not sign (a, bc)
		$_COOKIE['a'] = cookie::salt('ab', 'c').'~bc';
		$this->assertNull(cookie::get('a'));
	}

	public function test_changed_value_is_rejected()
	{
		$_COOKIE['name'] = cookie::salt('name', 'value').'~other';
		$this->assertSame('default', cookie::get('name', 'default'));

		$_COOKIE['name'] = 'value';
		$this->assertNull(cookie::get('name'));

		$_COOKIE['name'] = array('x' => 'y');
		$this->assertNull(cookie::get('name'));
	}

	public function test_old_sha1_signature_is_rejected()
	{
		$_SERVER['HTTP_USER_AGENT'] = 'KohanaTest/1.0';

		$_COOKIE['name'] = sha1('kohanatest/1.0'.'name'.'value'.CookieTest::SALT).'~value';
		$this->assertNull(cookie::get('name'));
	}

	public function test_user_agent_binding_is_optional()
	{
		$_SERVER['HTTP_USER_AGENT'] = 'Browser/1';
		$signature = cookie::salt('name', 'value');

		$_SERVER['HTTP_USER_AGENT'] = 'Browser/2';
		$this->assertSame($signature, cookie::salt('name', 'value'));

		$this->set_config('cookie.user_agent', TRUE);
		$bound = cookie::salt('name', 'value');
		$this->assertNotSame($signature, $bound);

		$_SERVER['HTTP_USER_AGENT'] = 'Browser/1';
		$this->assertNotSame($bound, cookie::salt('name', 'value'));
	}

	public function provider_refused_salts()
	{
		return array(
			array(''),
			array(NULL),
			array('K0hAN4 15 Th3 B357'),
		);
	}

	/**
	 * @dataProvider provider_refused_salts
	 */
	public function test_salt_must_be_set($salt)
	{
		$this->set_config('cookie.salt', $salt);

		try
		{
			cookie::salt('name', 'value');
			$this->fail('cookie::salt() accepted the salt');
		}
		catch (Kohana_Exception $e)
		{
			$this->assertStringContainsString('cookie.salt', $e->getMessage());
		}

		$this->expectException('Kohana_Exception');
		cookie::set('name', 'value');
	}

	public function test_shipped_defaults()
	{
		include SYSPATH.'config/cookie.php';

		$this->assertSame('', $config['salt']);
		$this->assertTrue($config['httponly']);
		$this->assertSame('Lax', $config['samesite']);
		$this->assertFalse($config['secure']);
		$this->assertFalse($config['user_agent']);

		$config = NULL;
		include SYSPATH.'config/session.php';

		$this->assertSame('native', $config['driver']);
	}
}
