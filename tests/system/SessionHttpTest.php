<?php
require_once DOCROOT.'tests/CgiTestCase.php';

/**
 * Session library and drivers in real requests. The database driver runs
 * only if KOHANA_TEST_MYSQL_HOST is set (see DatabaseMysqlTest).
 *
 * @group session
 */
class SessionHttpTest extends Kohana_CgiTestCase {

	const TABLE = 'kc_sessions';

	protected $driver_config = array();

	protected function tearDown(): void
	{
		if ($this->driver_config AND $this->driver_config['session.driver'] === 'database')
		{
			// Delete only the rows of this test
			Database::instance('kc_session')->query('DELETE FROM '.SessionHttpTest::TABLE);
		}

		parent::tearDown();
	}

	public function provider_drivers()
	{
		return array(
			array('native'),
			array('cookie'),
			array('cache'),
			array('database'),
		);
	}

	/**
	 * Selects a session driver for the following requests.
	 */
	protected function use_driver($driver)
	{
		$config = array('session.driver' => $driver);

		if ($driver === 'cache')
		{
			$config['session.storage'] = array(
				'driver' => 'file',
				'params' => array('directory' => $this->save_path, 'gc_probability' => 0),
				'lifetime' => 0,
				'requests' => 1000,
				'prefix' => NULL,
			);
		}
		elseif ($driver === 'database')
		{
			if ( ! getenv('KOHANA_TEST_MYSQL_HOST'))
				$this->markTestSkipped('Set KOHANA_TEST_MYSQL_HOST to run the session database tests');

			$group = array(
				'benchmark'     => FALSE,
				'persistent'    => FALSE,
				'connection'    => array(
					'type'     => 'mysqli',
					'user'     => getenv('KOHANA_TEST_MYSQL_USER'),
					'pass'     => getenv('KOHANA_TEST_MYSQL_PASSWORD'),
					'host'     => getenv('KOHANA_TEST_MYSQL_HOST'),
					'port'     => getenv('KOHANA_TEST_MYSQL_PORT') ?: FALSE,
					'socket'   => FALSE,
					'database' => getenv('KOHANA_TEST_MYSQL_DATABASE'),
					'params'   => NULL,
				),
				'character_set' => 'utf8mb4',
				'table_prefix'  => '',
				'object'        => TRUE,
				'cache'         => FALSE,
				'escape'        => TRUE,
			);

			Kohana_Config::instance()->set('database.kc_session', $group);

			Database::instance('kc_session')->query('CREATE TABLE IF NOT EXISTS '.SessionHttpTest::TABLE.' (session_id VARCHAR(127) NOT NULL, last_activity INT(10) UNSIGNED NOT NULL, data TEXT NOT NULL, PRIMARY KEY (session_id)) ENGINE=InnoDB');
			Database::instance('kc_session')->query('DELETE FROM '.SessionHttpTest::TABLE);

			$config['database.kc_session'] = $group;
			$config['session.storage'] = array('group' => 'kc_session', 'table' => SessionHttpTest::TABLE);
		}

		$this->driver_config = $config;
	}

	protected function default_config()
	{
		return $this->driver_config + parent::default_config();
	}

	protected function assertClean(array $response)
	{
		$this->assertNull($response['error']);
		$this->assertSame(array(), $response['notices']);
	}

	/**
	 * @dataProvider provider_drivers
	 */
	public function test_session_data_persists($driver)
	{
		$this->use_driver($driver);

		$first = $this->request(array(array('start'), array('set', 'user', 'alice')));
		$this->assertClean($first);
		$this->assertMatchesRegularExpression('/^[a-zA-Z0-9,-]{22,}$/D', $first['session_id']);

		$cookies = $this->next_cookies($first);
		$second = $this->request(array(array('start'), array('get', 'user')), $cookies);

		$this->assertClean($second);
		$this->assertSame($first['session_id'], $second['session_id']);
		$this->assertSame(array($first['session_id'], 'alice'), $second['results']);
	}

	/**
	 * @dataProvider provider_drivers
	 */
	public function test_regenerate_keeps_data_and_changes_id($driver)
	{
		$this->use_driver($driver);

		$first = $this->request(array(array('start'), array('set', 'user', 'alice')));
		$cookies = $this->next_cookies($first);

		$second = $this->request(array(array('start'), array('regenerate'), array('get', 'user')), $cookies);

		$this->assertClean($second);
		$this->assertNotSame($first['session_id'], $second['session_id']);
		$this->assertSame($second['session_id'], $second['results'][1]);
		$this->assertSame('alice', $second['results'][2]);

		$third = $this->request(array(array('start'), array('get', 'user')), $this->next_cookies($second, $cookies));

		$this->assertClean($third);
		$this->assertSame($second['session_id'], $third['session_id']);
		$this->assertSame('alice', $third['results'][1]);
	}

	/**
	 * @dataProvider provider_drivers
	 */
	public function test_regenerate_in_the_first_request($driver)
	{
		$this->use_driver($driver);

		$first = $this->request(array(array('start'), array('set', 'user', 'alice'), array('regenerate')));
		$this->assertClean($first);

		$second = $this->request(array(array('start'), array('get', 'user')), $this->next_cookies($first));

		$this->assertClean($second);
		$this->assertSame($first['results'][2], $second['session_id']);
		$this->assertSame('alice', $second['results'][1]);
	}

	public function provider_server_drivers()
	{
		return array(
			array('native'),
			array('cache'),
			array('database'),
		);
	}

	/**
	 * @dataProvider provider_server_drivers
	 */
	public function test_regenerate_ends_the_old_session($driver)
	{
		$this->use_driver($driver);

		$first = $this->request(array(array('start'), array('set', 'user', 'alice')));
		$old = $this->next_cookies($first);

		$this->request(array(array('start'), array('regenerate')), $old);

		// The old signed cookie must not open the session again
		$replay = $this->request(array(array('start'), array('get', 'user')), $old);

		$this->assertClean($replay);
		$this->assertFalse($replay['results'][1]);
	}

	/**
	 * @dataProvider provider_drivers
	 */
	public function test_destroy_removes_the_data($driver)
	{
		$this->use_driver($driver);

		$first = $this->request(array(array('start'), array('set', 'user', 'alice')));
		$cookies = $this->next_cookies($first);

		$second = $this->request(array(array('start'), array('destroy')), $cookies);
		$this->assertClean($second);

		$third = $this->request(array(array('start'), array('get', 'user')), $driver === 'cookie' ? $this->next_cookies($second, $cookies) : $cookies);

		$this->assertClean($third);
		$this->assertFalse($third['results'][1]);
	}

	/**
	 * @dataProvider provider_drivers
	 */
	public function test_unsigned_chosen_id_is_not_adopted($driver)
	{
		$this->use_driver($driver);

		$response = $this->request(array(array('start')), array('kohanasession' => 'attackerchosen1234567890'));

		$this->assertClean($response);
		$this->assertNotSame('attackerchosen1234567890', $response['session_id']);
		$this->assertStringEndsWith('~'.$response['session_id'], $response['cookies']['kohanasession']['value']);

		$response = $this->request(array(array('start')), array(), array(), array('QUERY_STRING' => 'kohanasession=attackerchosen1234567890'));

		$this->assertClean($response);
		$this->assertNotSame('attackerchosen1234567890', $response['session_id']);
	}

	/**
	 * @dataProvider provider_server_drivers
	 */
	public function test_signed_unknown_id_is_not_adopted($driver)
	{
		$this->use_driver($driver);

		$this->set_config('cookie.salt', Kohana_CgiTestCase::SALT);
		$this->set_config('cookie.user_agent', FALSE);

		$id = 'attackerchosen1234567890';
		$response = $this->request(array(array('start')), array('kohanasession' => cookie::salt('kohanasession', $id).'~'.$id));

		$this->assertClean($response);
		$this->assertNotSame($id, $response['session_id']);
		$this->assertStringEndsWith('~'.$response['session_id'], $response['cookies']['kohanasession']['value']);
	}

	public function test_php_sends_no_unsigned_session_cookie()
	{
		$response = $this->request(array(array('start'), array('regenerate')));

		$this->assertClean($response);

		foreach ($response['headers'] as $header)
		{
			if (stripos($header, 'Set-Cookie: kohanasession=') === 0)
			{
				$this->assertMatchesRegularExpression('/^Set-Cookie: kohanasession=[0-9a-f]{64}~/', $header);
			}
		}
	}
}
