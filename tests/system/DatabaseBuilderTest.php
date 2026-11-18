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
}
