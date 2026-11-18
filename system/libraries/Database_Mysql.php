<?php defined('SYSPATH') or die('No direct script access.');
/**
 * MySQL database connection.
 *
 * @package    Kohana
 * @author     Kohana Team
 * @copyright  (c) 2008-2009 Kohana Team
 * @license    http://kohanaphp.com/license
 */
defined('RUNS_MYSQLND') OR define('RUNS_MYSQLND', function_exists('mysqli_fetch_all'));

class Database_Mysql_Core extends Database {

	// Quote character to use for identifiers (tables/columns/aliases)
	protected $quote = '`';

	/**
	 * PHP 7.0 removed ext/mysql, so this driver uses MySQLi. Both "mysql"
	 * and "mysqli" configurations use it.
	 */
	public function connect()
	{
		if (is_object($this->connection))
			return;

		extract($this->config['connection'] + array(
			'host'     => NULL,
			'socket'   => NULL,
			'port'     => NULL,
			'user'     => NULL,
			'pass'     => NULL,
			'database' => NULL,
			'params'   => 0,
		));

		$host = isset($host) ? $host : $socket;

		if ($this->config['persistent'] === TRUE AND strncmp((string) $host, 'p:', 2) !== 0)
		{
			$host = 'p:'.$host;
		}

		$mysqli = mysqli_init();

		try
		{
			$connected = $mysqli->real_connect($host, $user, $pass, $database, $port === NULL ? NULL : (int) $port, $socket, (int) $params);
		}
		catch (mysqli_sql_exception $e)
		{
			// PHP 8.1 enables mysqli exceptions by default
			$connected = FALSE;
		}

		if ( ! $connected)
			throw new Database_Exception('#:errno: :error',
				array(':error' => $mysqli->connect_error, ':errno' => $mysqli->connect_errno));

		$this->connection = $mysqli;

		if (isset($this->config['character_set']))
		{
			// Set the character set
			$this->set_charset($this->config['character_set']);
		}
	}

	public function disconnect()
	{
		if (is_object($this->connection))
		{
			try
			{
				$this->connection->close();
			}
			catch (Throwable $e)
			{
				// The connection is already closed
			}
		}

		$this->connection = NULL;

		return TRUE;
	}

	public function set_charset($charset)
	{
		// Make sure the database is connected
		is_object($this->connection) or $this->connect();

		try
		{
			$status = $this->connection->set_charset($charset);
		}
		catch (mysqli_sql_exception $e)
		{
			$status = FALSE;
		}

		if ( ! $status)
		{
			// Unable to set charset
			throw new Database_Exception('#:errno: :error',
				array(':error' => $this->connection->error,
				':errno' => $this->connection->errno));
		}
	}

	public function query_execute($sql)
	{
		// Make sure the database is connected
		is_object($this->connection) or $this->connect();

		try
		{
			$result = $this->connection->query($sql);
		}
		catch (mysqli_sql_exception $e)
		{
			// Database_Mysqli_Result throws a Database_Exception for FALSE
			$result = FALSE;
		}

		// Set the last query
		$this->last_query = $sql;

		return new Database_Mysqli_Result($result, $sql, $this->connection, $this->config['object']);
	}

	public function escape($value)
	{
		// Make sure the database is connected
		is_object($this->connection) or $this->connect();

		return $this->connection->real_escape_string((string) $value);
	}

	public function list_constraints($table)
	{
		$prefix = strlen($this->table_prefix());
		$result = array();

		$constraints = $this->query('
			SELECT c.constraint_name, c.constraint_type, k.column_name, k.referenced_table_name, k.referenced_column_name
			FROM information_schema.table_constraints c
			JOIN information_schema.key_column_usage k ON (k.table_schema = c.table_schema AND k.table_name = c.table_name AND k.constraint_name = c.constraint_name)
			WHERE c.table_schema = '.$this->quote($this->config['connection']['database']).'
				AND c.table_name = '.$this->quote($this->table_prefix().$table).'
				AND (k.referenced_table_schema IS NULL OR k.referenced_table_schema ='.$this->quote($this->config['connection']['database']).')
			ORDER BY k.ordinal_position
		');

		foreach ($constraints->as_array() as $row)
		{
			switch ($row['constraint_type'])
			{
				case 'FOREIGN KEY':
					if (isset($result[$row['constraint_name']]))
					{
						$result[$row['constraint_name']][1][] = $row['column_name'];
						$result[$row['constraint_name']][3][] = $row['referenced_column_name'];
					}
					else
					{
						$result[$row['constraint_name']] = array($row['constraint_type'], array($row['column_name']), substr($row['referenced_table_name'], $prefix), array($row['referenced_column_name']));
					}
				break;
				case 'PRIMARY KEY':
				case 'UNIQUE':
					if (isset($result[$row['constraint_name']]))
					{
						$result[$row['constraint_name']][1][] = $row['column_name'];
					}
					else
					{
						$result[$row['constraint_name']] = array($row['constraint_type'], array($row['column_name']));
					}
				break;
			}
		}

		return $result;
	}

	public function list_fields($table)
	{
		$result = array();

		foreach ($this->query('SHOW COLUMNS FROM '.$this->quote_table($table))->as_array() as $row)
		{
			$column = $this->sql_type($row['Type']);

			$column['default'] = $row['Default'];
			$column['nullable'] = $row['Null'] === 'YES';
			$column['sequenced'] = $row['Extra'] === 'auto_increment';

			if (isset($column['length']) AND $column['type'] === 'float')
			{
				list($column['precision'], $column['scale']) = explode(',', $column['length']);
			}

			$result[$row['Field']] = $column;
		}

		return $result;
	}

	public function list_tables()
	{
		$prefix = strlen($this->table_prefix());
		$tables = array();

		foreach ($this->query('SHOW TABLES LIKE '.$this->quote($this->table_prefix().'%'))->as_array() as $row)
		{
			// The value is the table name
			$tables[] = substr(current($row), $prefix);
		}

		return $tables;
	}

} // End Database_MySQL
