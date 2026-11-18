<?php
/**
 * Database driver that only compiles SQL. It never connects.
 */
class Database_Kb_Stub extends Database {

	public static function factory($quote = '`', array $config = array())
	{
		$db = new Database_Kb_Stub($config + array(
			'benchmark'     => FALSE,
			'persistent'    => FALSE,
			'connection'    => array('type' => 'kb_stub'),
			'character_set' => 'utf8',
			'table_prefix'  => '',
			'object'        => TRUE,
			'cache'         => FALSE,
			'escape'        => TRUE,
		));

		$db->quote = $quote;

		return $db;
	}

	public function connect() {}
	public function disconnect() {}
	public function set_charset($charset) {}
	public function list_constraints($table) { return array(); }
	public function list_fields($table) { return array(); }
	public function list_tables() { return array(); }

	public function query_execute($sql)
	{
		throw new Database_Exception('Database_Kb_Stub does not execute queries');
	}

	public function escape($value)
	{
		return addslashes($value);
	}
}
