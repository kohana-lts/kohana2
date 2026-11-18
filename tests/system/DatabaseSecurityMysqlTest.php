<?php
/**
 * Query builder and ORM security tests on MySQL. See DatabaseMysqlTest for
 * the environment variables.
 *
 * @group database
 */
class DatabaseSecurityMysqlTest extends Kohana_TestCase {

	/**
	 * @var Database
	 */
	protected $db;

	protected function setUp(): void
	{
		parent::setUp();

		if ( ! getenv('KOHANA_TEST_MYSQL_HOST'))
		{
			$this->markTestSkipped('Set KOHANA_TEST_MYSQL_HOST to run the MySQL tests');
		}

		Kohana_Config::instance()->set('database.kb_mysqli', array(
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
		));

		$this->db = Database::instance('kb_mysqli');
		$this->db->query('CREATE TABLE IF NOT EXISTS kb_users (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL, role VARCHAR(32) NOT NULL DEFAULT \'user\') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
		$this->db->query('DELETE FROM kb_users');
		$this->db->query('ALTER TABLE kb_users AUTO_INCREMENT = 1');

		db::insert('kb_users', array('name' => 'alice', 'role' => 'admin'))->execute($this->db);
		db::insert('kb_users', array('name' => 'b_b', 'role' => 'user'))->execute($this->db);
		db::insert('kb_users', array('name' => 'bxb', 'role' => 'user'))->execute($this->db);
	}

	public function test_identifier_with_backtick_stays_one_identifier()
	{
		try
		{
			db::select()->from('kb_users')->where('id` = 0 OR 1 = 1 OR `id', '=', 0)->execute($this->db);
			$this->fail('The query must not run');
		}
		catch (Database_Exception $e)
		{
			$this->assertStringContainsString('Unknown column', $e->getMessage());
		}
	}

	public function test_order_by_direction_injection_throws()
	{
		$this->expectException(Database_Exception::class);

		db::select()->from('kb_users')->order_by('id', 'ASC, (SELECT SLEEP(5))')->execute($this->db);
	}

	public function test_order_by_random_expression()
	{
		$rows = db::select()->from('kb_users')->order_by(db::expr('RAND()'))->execute($this->db);

		$this->assertCount(3, $rows);
	}

	public function test_function_form_and_escape_like()
	{
		$count = db::select(array('n' => 'COUNT(DISTINCT "kb_users.role")'))->from('kb_users')->execute($this->db)->get('n');
		$this->assertEquals(2, $count);

		$rows = db::select('name')->from('kb_users')->where('name', 'LIKE', db::escape_like('b_b'))->execute($this->db);
		$this->assertCount(1, $rows);

		$rows = db::select('name')->from('kb_users')->where('name', 'LIKE', 'b_b')->execute($this->db);
		$this->assertCount(2, $rows);
	}
}
