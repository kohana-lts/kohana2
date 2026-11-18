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

		$this->db->query('CREATE TABLE IF NOT EXISTS kb_posts (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, kb_user_id INT UNSIGNED NOT NULL, title VARCHAR(255) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
		$this->db->query('DELETE FROM kb_posts');
		$this->db->query('ALTER TABLE kb_posts AUTO_INCREMENT = 1');

		db::insert('kb_users', array('name' => 'alice', 'role' => 'admin'))->execute($this->db);
		db::insert('kb_posts', array('kb_user_id' => 1, 'title' => 'hello'))->execute($this->db);
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

	public function test_orm_order_by_direction_injection_throws()
	{
		$this->expectException(Database_Exception::class);

		ORM::factory('kb_user')->order_by('id', 'ASC, (SELECT SLEEP(5))')->find_all();
	}

	public function test_orm_validate_loads_only_fields_with_rules()
	{
		$user = ORM::factory('kb_user');
		$user->validate(new Validation(array('name' => 'eve', 'role' => 'admin', 'id' => 1)));
		$user->save();

		$this->assertSame('eve', $user->name);
		$this->assertSame('user', ORM::factory('kb_user', $user->id)->role);
		$this->assertEquals(4, $user->id);
	}

	public function test_orm_set_array_refuses_primary_key()
	{
		$this->expectException(Kohana_Exception::class);

		ORM::factory('kb_user', 2)->set(array('id' => 1, 'name' => 'x'));
	}

	public function test_orm_set_array_refuses_many_to_many_relations()
	{
		$this->expectException(Kohana_Exception::class);

		ORM::factory('kb_user', 2)->set(array('kb_groups' => array(1)));
	}

	public function test_orm_set_array_with_expected_keys()
	{
		$input = array('name' => 'bob', 'role' => 'admin', 'id' => 1, 'submit' => 'Save');

		$user = ORM::factory('kb_user', 2)->set($input, array('name'));
		$user->save();

		$user = ORM::factory('kb_user', 2);
		$this->assertSame('bob', $user->name);
		$this->assertSame('user', $user->role);
		$this->assertSame('alice', ORM::factory('kb_user', 1)->name);
	}

	public function test_orm_set_array_expected_keys_allow_primary_key()
	{
		$user = ORM::factory('kb_user')->set(array('id' => 9, 'name' => 'nat'), array('id', 'name'));
		$user->save();

		$this->assertSame('nat', ORM::factory('kb_user', 9)->name);
	}

	public function test_orm_load_values_skips_primary_key()
	{
		$user = ORM::factory('kb_user', 2)->load_values(array('id' => 1, 'name' => 'x'));
		$user->save();

		$this->assertSame('x', ORM::factory('kb_user', 2)->name);
		$this->assertSame('alice', ORM::factory('kb_user', 1)->name);
	}

	public function test_orm_protected_columns_opt_out()
	{
		$user = ORM::factory('kb_open_user')->set(array('id' => 7, 'name' => 'open'));
		$user->save();

		$this->assertSame('open', ORM::factory('kb_user', 7)->name);
	}

	public function test_orm_with_joins_related_object()
	{
		$post = ORM::factory('kb_post')->with('kb_user')->find(1);

		$this->assertSame('hello', $post->title);
		$this->assertSame('alice', $post->kb_user->name);
	}
}

class Kb_User_Model extends ORM {

	protected $db = 'kb_mysqli';

	protected $has_and_belongs_to_many = array('kb_groups');

	protected $rules = array(
		'name' => array('rules' => array('required')),
	);
}

class Kb_Open_User_Model extends ORM {

	protected $db = 'kb_mysqli';

	protected $table_name = 'kb_users';

	protected $protected_columns = array();
}

class Kb_Post_Model extends ORM {

	protected $db = 'kb_mysqli';

	protected $belongs_to = array('kb_user');
}
