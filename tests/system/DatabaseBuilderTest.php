<?php
require_once __DIR__.'/DatabaseStub.php';

/**
 * Query builder tests. They compile SQL with a stub driver and need no server.
 */
class DatabaseBuilderTest extends Kohana_TestCase {

	protected function builder($quote = '`')
	{
		return new Database_Builder(Database_Kb_Stub::factory($quote));
	}

	public function provider_order_by_bad_direction()
	{
		return array(
			array('ASC, (SELECT SLEEP(5))'),
			array('ASC; DROP TABLE t'),
			array('DESC LIMIT 1'),
			array('RAND()'),
			array('ASC DESC'),
			array(1),
			array(array('ASC')),
		);
	}

	/**
	 * @dataProvider provider_order_by_bad_direction
	 */
	public function test_order_by_rejects_bad_direction($direction)
	{
		$this->expectException(Database_Exception::class);

		(string) $this->builder()->select()->from('t')->order_by('id', $direction);
	}

	/**
	 * @dataProvider provider_order_by_bad_direction
	 */
	public function test_order_by_array_rejects_bad_direction($direction)
	{
		$this->expectException(Database_Exception::class);

		(string) $this->builder()->select()->from('t')->order_by(array('id' => $direction));
	}

	public function test_order_by_accepts_valid_directions()
	{
		$sql = (string) $this->builder()->select()->from('t')
			->order_by('a', 'asc')
			->order_by('b', ' Desc ')
			->order_by('c')
			->order_by('g', '')
			->order_by(array('d' => 'DESC', 'e', 'f' => NULL));

		$this->assertStringEndsWith('ORDER BY `a` ASC, `b` DESC, `c`, `g`, `d` DESC, `e`, `f`', $sql);
	}

	public function test_order_by_accepts_expressions()
	{
		$sql = (string) $this->builder()->select()->from('t')
			->order_by(db::expr('RAND()'))
			->order_by('id', db::expr('DESC NULLS LAST'));

		$this->assertStringEndsWith('ORDER BY RAND(), `id` DESC NULLS LAST', $sql);
	}

	public function provider_quotes()
	{
		return array(array('`'), array('"'));
	}

	/**
	 * @dataProvider provider_quotes
	 */
	public function test_quote_column_doubles_quote_characters($q)
	{
		$db = Database_Kb_Stub::factory($q);

		$this->assertSame($q.'a'.$q.$q.'b'.$q, $db->quote_identifier('a'.$q.'b'));
		$this->assertSame('`a``b`', Database_Kb_Stub::factory('`')->quote_column('a`b'));
		$this->assertSame('`t`.`c``x`', Database_Kb_Stub::factory('`')->quote_column('t.c`x'));
		$this->assertSame("`COUNT('x')`", Database_Kb_Stub::factory('`')->quote_column("COUNT('x')"));
		$this->assertSame($q.'t'.$q.'.*', $db->quote_column('t.*'));
		$this->assertSame('*', $db->quote_column('*'));
		$this->assertSame($q.'a'.$q.' AS '.$q.'x'.$q.$q.$q, $db->quote_column('a', 'x'.$q));
	}

	/**
	 * @dataProvider provider_quotes
	 */
	public function test_quote_table_doubles_quote_characters($q)
	{
		$db = Database_Kb_Stub::factory($q);

		$this->assertSame($q.'a'.$q.$q.'b'.$q, $db->quote_table('a'.$q.'b'));
		$this->assertSame($q.'users'.$q.' AS '.$q.'u'.$q.$q.$q, $db->quote_table('users', 'u'.$q));
	}

	public function test_quote_table_splits_alias()
	{
		$db = Database_Kb_Stub::factory('`', array('table_prefix' => 'p_'));

		$this->assertSame('`p_users` AS `u`', $db->quote_table('users u'));
		$this->assertSame('`p_users` AS `u`', $db->quote_table('users  AS  u'));
		$this->assertSame('`p_users` AS `u`', $db->quote_table(array('u' => 'users')));
		$this->assertSame('(SELECT 1) AS `s`', $db->quote_table(array('s' => db::expr('(SELECT 1)'))));
		$this->assertSame('(SELECT 1) x', $db->quote_table(db::expr('(SELECT 1) x')));
	}

	public function test_quote_table_rejects_bad_alias_format()
	{
		$this->expectException(Database_Exception::class);

		Database_Kb_Stub::factory()->quote_table('users u; DROP');
	}

	public function provider_quoted_expressions()
	{
		return array(
			array('COUNT("*")', 'COUNT(*)'),
			array('"users.id"', '`users`.`id`'),
			array('"users.*"', '`users`.*'),
			array('SUM("column")', 'SUM(`column`)'),
			array('COUNT(DISTINCT "users.id")', 'COUNT(DISTINCT `users`.`id`)'),
			array('IFNULL(SUM("a"), 0)', 'IFNULL(SUM(`a`), 0)'),
			array('ROUND(AVG("t.score"), 2)', 'ROUND(AVG(`t`.`score`), 2)'),
			array('NOW("x")', 'NOW(`x`)'),
			array('IFNULL("a",0)', 'IFNULL(`a`, 0)'),
			array('"a`b"', '`a``b`'),
		);
	}

	/**
	 * @dataProvider provider_quoted_expressions
	 */
	public function test_quote_column_keeps_function_form($column, $expected)
	{
		$this->assertSame($expected, Database_Kb_Stub::factory()->quote_column($column));
	}

	public function test_quote_column_function_form_uses_prefix()
	{
		$db = Database_Kb_Stub::factory('`', array('table_prefix' => 'p_'));

		$this->assertSame('COUNT(`p_users`.`id`)', $db->quote_column('COUNT("users.id")'));
		$this->assertSame('`p_users`.`id`', $db->quote_column('users.id'));
	}

	public function provider_bad_quoted_columns()
	{
		return array(
			array('x"+(select 1)+"y'),
			array('COUNT("a") FROM t UNION SELECT "b"'),
			array('"a" UNION SELECT "b"'),
			array('"a"; DROP TABLE t'),
			array('"a" -- '),
			array('COUNT("a"'),
			array('"a")'),
			array('"a" "b"'),
			array('""'),
			array('COUNT(\'x\', "a")'),
			array('"a", 0'),
			array('COUNT(DISTINCT, "a")'),
			array('0 "a"'),
			array('COUNT("a",)'),
			array('"a" OR 1'),
		);
	}

	/**
	 * @dataProvider provider_bad_quoted_columns
	 */
	public function test_quote_column_rejects_raw_sql($column)
	{
		$this->expectException(Database_Exception::class);

		Database_Kb_Stub::factory()->quote_column($column);
	}

	public function test_quote_column_legacy_mode_passes_raw_sql()
	{
		Database::$legacy_column_expressions = TRUE;

		try
		{
			$this->assertSame('CONCAT(`a`, \' \', `b`)', Database_Kb_Stub::factory()->quote_column('CONCAT("a", \' \', "b")'));
		}
		finally
		{
			Database::$legacy_column_expressions = FALSE;
		}
	}

	public function test_where_column_injection_is_quoted()
	{
		$sql = (string) $this->builder()->select()->from('t')->where('a`b', '=', 1);

		$this->assertStringEndsWith('WHERE `a``b` = 1', $sql);
	}

	public function test_order_by_column_injection_is_rejected()
	{
		$this->expectException(Database_Exception::class);

		(string) $this->builder()->select()->from('t')->order_by('x"+(select 1)+"y');
	}

	public function provider_bad_operators()
	{
		return array(
			array('= 1 OR 1 ='),
			array('= 1 OR'),
			array('LIKE 1 OR'),
			array(';'),
			array(''),
			array(NULL),
		);
	}

	/**
	 * @dataProvider provider_bad_operators
	 */
	public function test_where_rejects_unknown_operator($op)
	{
		$this->expectException(Database_Exception::class);

		(string) $this->builder()->select()->from('t')->where('id', $op, 1);
	}

	/**
	 * @dataProvider provider_bad_operators
	 */
	public function test_having_and_triplets_reject_unknown_operator($op)
	{
		$this->expectException(Database_Exception::class);

		(string) $this->builder()->select()->from('t')->having(array(array('id', $op, 1)));
	}

	public function test_conditions_accept_known_operators()
	{
		$sql = (string) $this->builder()->select()->from('t')
			->where('a', '=', 1)
			->where('b', 'not  in', array(1, 2))
			->where('c', 'is not', NULL)
			->where('d', 'Between', array(1, 2))
			->where('e', 'LIKE', 'x%')
			->where('', 'NOT EXISTS', $this->builder()->select('id')->from('u'))
			->where('f', '<=>', 3);

		$this->assertStringContainsString("WHERE `a` = 1 AND `b` NOT IN (1, 2) AND `c` IS NOT NULL AND `d` BETWEEN 1 AND 2 AND `e` LIKE 'x%' AND  NOT EXISTS (SELECT `id`", $sql);
		$this->assertStringEndsWith('AND `f` <=> 3', $sql);
	}

	public function test_operators_can_be_extended()
	{
		$operators = Database_Builder::$operators;
		Database_Builder::$operators[] = '@@';

		try
		{
			$sql = (string) $this->builder()->select()->from('t')->where('doc', '@@', 'x');
			$this->assertStringEndsWith("WHERE `doc` @@ 'x'", $sql);
		}
		finally
		{
			Database_Builder::$operators = $operators;
		}
	}

	public function test_join_rejects_unknown_type()
	{
		$this->expectException(Database_Exception::class);

		$this->builder()->select()->from('t')->join('u', 'u.id', 't.u_id', 'LEFT JOIN x ON 1=1 LEFT');
	}

	public function test_join_accepts_builder_types()
	{
		$b = $this->builder()->select()->from('t')
			->left_join('a', 'a.id', 't.a_id')
			->right_join('b', 'b.id', 't.b_id')
			->inner_join('c', 'c.id', 't.c_id')
			->outer_join('d', 'd.id', 't.d_id')
			->full_join('e', 'e.id', 't.e_id')
			->left_inner_join('f', 'f.id', 't.f_id')
			->right_inner_join('g', 'g.id', 't.g_id')
			->join('h', 'h.id', 't.h_id', 'left outer')
			->join('i', 'i.id', 't.i_id');

		$sql = (string) $b;
		$this->assertStringContainsString(' LEFT OUTER JOIN `h` ON (`h`.`id` = `t`.`h_id`) JOIN `i`', $sql);
	}

	public function test_escape_like()
	{
		$this->assertSame('100\\%\\_a\\\\b', db::escape_like('100%_a\\b'));
		$this->assertSame('100!%!_a!!b', db::escape_like('100%_a!b', '!'));
	}
}
