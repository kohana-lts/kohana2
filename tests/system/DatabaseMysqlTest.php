<?php
/**
 * Database and ORM tests on MySQL. Set KOHANA_TEST_MYSQL_HOST, _PORT, _USER,
 * _PASSWORD and _DATABASE to run them.
 *
 * @group database
 */
class DatabaseMysqlTest extends Kohana_TestCase {

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

		foreach (array('mysql', 'mysqli') as $type)
		{
			Kohana_Config::instance()->set('database.kt_'.$type, array(
				'benchmark'     => FALSE,
				'persistent'    => FALSE,
				'connection'    => array(
					'type'     => $type,
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
		}

		$this->db = Database::instance('kt_mysql');
		// Never drop tables: create the table once, then empty it and reset the ids
		$this->db->query('CREATE TABLE IF NOT EXISTS kt_items (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL, score INT NOT NULL DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
		$this->db->query('DELETE FROM kt_items');
		$this->db->query('ALTER TABLE kt_items AUTO_INCREMENT = 1');
	}

	protected function tearDown(): void
	{
		if ($this->db)
		{
			$this->db->query('DELETE FROM kt_items');
		}

		parent::tearDown();
	}

	public function provider_types()
	{
		return array(array('kt_mysql'), array('kt_mysqli'));
	}

	/**
	 * @dataProvider provider_types
	 */
	public function test_builder_crud($group)
	{
		$db = Database::instance($group);

		$result = db::insert('kt_items', array('name' => 'one', 'score' => 1))->execute($db);
		$this->assertEquals(1, $result->insert_id());

		db::insert('kt_items', array('name' => 'two', 'score' => 2))->execute($db);

		$rows = db::select('name', 'score')->from('kt_items')->order_by('id', 'ASC')->execute($db)->as_array(TRUE);
		$this->assertCount(2, $rows);
		$this->assertEquals('one', $rows[0]['name']);

		$updated = db::update('kt_items', array('score' => 10), array('name', '=', 'two'))->execute($db);
		$this->assertSame(1, $updated->count());

		db::delete('kt_items', array('name', '=', 'one'))->execute($db);
		$this->assertSame(1, db::select()->from('kt_items')->execute($db)->count());
	}

	/**
	 * @dataProvider provider_types
	 */
	public function test_quoted_values_round_trip($group)
	{
		$db = Database::instance($group);

		$values = array("O'Reilly", 'double "quote"', 'back\\slash', "\\'; DROP TABLE kt_items; --", "multi\nline", 'ünïcödé ✓');

		foreach ($values as $value)
		{
			db::insert('kt_items', array('name' => $value))->execute($db);

			$found = db::select('name')->from('kt_items')->where('name', '=', $value)->execute($db)->current();

			$this->assertNotNull($found);
			$this->assertSame($value, $found->name);
		}
	}

	/**
	 * @dataProvider provider_types
	 */
	public function test_query_error_throws($group)
	{
		$this->expectException(Database_Exception::class);

		Database::instance($group)->query('SELECT * FROM kt_missing_table');
	}

	public function test_orm_crud()
	{
		$item = ORM::factory('kt_item');
		$item->name = 'first';
		$item->score = 3;
		$item->save();

		$this->assertTrue($item->loaded());
		$this->assertEquals(1, $item->id);

		$found = ORM::factory('kt_item', 1);
		$this->assertSame('first', $found->name);

		$found->score = 7;
		$found->save();
		$this->assertEquals(7, ORM::factory('kt_item', 1)->score);

		ORM::factory('kt_item')->set('name', 'second')->save();
		$this->assertEquals(2, ORM::factory('kt_item')->count_all());
		$this->assertCount(2, ORM::factory('kt_item')->find_all());

		$found->delete();
		$this->assertFalse(ORM::factory('kt_item', 1)->loaded());
	}
}

class Kt_Item_Model extends ORM {

	protected $db = 'kt_mysql';
}
